<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\DepositStatusChanged;
use App\Events\WithdrawalStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminTransactionController extends Controller
{
    public function bindVerifiedBinanceSender(Request $request, \App\Models\User $user): JsonResponse
    {
        $data = $request->validate([
            'binance_uid' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'verification_note' => ['required', 'string', 'min:12', 'max:1000'],
        ]);
        try {
            DB::transaction(function () use ($request, $user, $data) {
                // Both constraints are UNIQUE in DB, even under simultaneous requests.
                DB::table('verified_binance_senders')->updateOrInsert(
                    ['user_id' => $user->id],
                    ['binance_uid' => $data['binance_uid'], 'verification_note' => $data['verification_note'],
                        'verified_by_admin_id' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()]
                );
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json(['message' => 'Binance UID is already linked to another website account.'], 409);
        }
        return response()->json(['user_id' => $user->id, 'binance_uid' => $data['binance_uid'], 'status' => 'admin_verified']);
    }

    public function deposits(Request $request): JsonResponse
    {
        $query = Transaction::with('user')
            ->where('type', TransactionType::Deposit)
            ->where(function ($q) {
                $q->where('meta->stage', '!=', 'awaiting_transfer')
                  ->orWhereNull('meta->stage');
            });

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Search the full archive before pagination. Never delete records on approval/rejection.
        if ($request->filled('search')) {
            $needle = trim((string) $request->input('search'));
            $query->where(function ($q) use ($needle) {
                $q->where('payment_reference', 'like', '%'.$needle.'%')
                    ->orWhere('method', 'like', '%'.$needle.'%')
                    ->orWhere('gateway_ref', 'like', '%'.$needle.'%')
                    ->orWhere('meta', 'like', '%'.$needle.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$needle.'%')->orWhere('email', 'like', '%'.$needle.'%'));
                if (ctype_digit($needle)) $q->orWhere('id', (int) $needle)->orWhere('user_id', (int) $needle);
            });
        }
        // HAIDAR-V5-SEARCH-DEPOSITS: filter first, paginate the full financial ledger.
        if ($request->filled('search')) {
            $needle = mb_substr(trim((string) $request->input('search')), 0, 120);
            $query->where(function ($q) use ($needle) {
                $q->where('payment_reference', 'like', '%'.$needle.'%')
                  ->orWhere('gateway_ref', 'like', '%'.$needle.'%')
                  ->orWhere('method', 'like', '%'.$needle.'%')
                  ->orWhere('meta', 'like', '%'.$needle.'%')
                  ->orWhereHas('user', function ($uq) use ($needle) {
                      $uq->where('name', 'like', '%'.$needle.'%')->orWhere('email', 'like', '%'.$needle.'%');
                  });
                if (ctype_digit($needle)) $q->orWhere('id', (int) $needle)->orWhere('user_id', (int) $needle);
            });
        }
        if ($request->filled('method')) $query->where('method', (string) $request->input('method'));
        if ($request->filled('date_from')) {
            $request->validate(['date_from' => 'date_format:Y-m-d']);
            $query->whereDate('created_at', '>=', (string) $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $request->validate(['date_to' => 'date_format:Y-m-d']);
            $query->whereDate('created_at', '<=', (string) $request->input('date_to'));
        }

        $deposits = $query->latest('id')->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return response()->json($deposits);
    }

    /** Lightweight, authenticated endpoint for the admin notification widget. */
    public function pendingAlerts(): JsonResponse
    {
        $query = Transaction::query()->where('type', TransactionType::Deposit)
            ->where('status', TransactionStatus::Pending)
            ->where('meta->stage', 'awaiting_admin')
            ->whereNotNull('payment_reference');
        $count = (clone $query)->count();
        $items = $query->orderByDesc('id')->limit(20)->get()
            ->filter(fn ($transaction) => ($transaction->meta['stage'] ?? null) === 'awaiting_admin')
            ->map(fn ($transaction) => [
                'id' => $transaction->id,
                'amount' => (string) $transaction->amount,
                'transfer_amount' => $transaction->meta['transfer_amount'] ?? $transaction->amount,
                'transfer_currency' => $transaction->meta['transfer_currency'] ?? 'USD',
                'method' => $transaction->method,
                'submitted_at' => $transaction->meta['submitted_at'] ?? $transaction->created_at?->toIso8601String(),
            ])->values();
        return response()->json(['pending_count' => $count, 'items' => $items]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $query = Transaction::with('user')
            ->where('type', TransactionType::Withdrawal);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $withdrawals = $query->latest()->paginate(25);

        return response()->json($withdrawals);
    }

    public function showDeposit(Transaction $transaction): JsonResponse
    {
        if ($transaction->type !== TransactionType::Deposit) {
            return response()->json(['message' => 'Not a deposit.'], 404);
        }
        return response()->json(['transaction' => $transaction->load('user')]);
    }

    public function receipt(Transaction $transaction)
    {
        if ($transaction->type !== TransactionType::Deposit) abort(404);
        $path = $transaction->meta['receipt_path'] ?? null;
        if (!is_string($path) || !str_starts_with($path, 'deposit-receipts/') || !Storage::disk('local')->exists($path)) abort(404);
        return Storage::disk('local')->response($path, 'deposit-'.$transaction->id);
    }

    public function approveDeposit(Request $request, Transaction $transaction): JsonResponse
    {
        try {
        $approved = DB::transaction(function () use ($request, $transaction) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->type !== TransactionType::Deposit || $locked->status !== TransactionStatus::Pending) {
                return false;
            }
            if (($locked->meta['stage'] ?? null) !== 'awaiting_admin') {
                throw new \DomainException('Customer has not submitted a reviewable receipt yet.');
            }
            if (!is_string($locked->payment_reference) || trim($locked->payment_reference) === '') {
                throw new \DomainException('Deposit transfer reference is missing.');
            }
            $meta = $locked->meta ?? [];
            if (empty($meta['receipt_path']) || !Storage::disk('local')->exists($meta['receipt_path'])) {
                throw new \DomainException('Receipt is missing; cannot approve unverified deposit.');
            }
            // Approving admin is responsible for checking the payment provider before clicking approve.
            // Never infer actual payment from a customer receipt or record alone.
            $meta['verified_by_admin_id'] = $request->user()->id;
            $meta['verified_at'] = now()->toIso8601String();
            $meta['verified_external_reference'] = trim($locked->payment_reference);
            $user = \App\Models\User::whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => TransactionStatus::Approved, 'meta' => $meta]);
            $user->increment('balance', $locked->amount);
            return true;
        });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if (!$approved) {
            return response()->json(['message' => 'Deposit already processed or not pending.'], 409);
        }
        $this->safeBroadcast(new DepositStatusChanged($transaction->fresh()));
        return response()->json(['transaction' => $transaction->fresh()]);
    }

    public function rejectDeposit(Request $request, Transaction $transaction): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $rejected = DB::transaction(function () use ($transaction, $data) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->type !== TransactionType::Deposit || $locked->status !== TransactionStatus::Pending ||
                ($locked->meta['stage'] ?? null) !== 'awaiting_admin') {
                return false;
            }
            $locked->update([
                'status' => TransactionStatus::Rejected,
                'rejection_reason' => $data['reason'] ?? null,
            ]);
            return true;
        });
        if (!$rejected) {
            return response()->json(['message' => 'Deposit already processed or not pending.'], 409);
        }
        $this->safeBroadcast(new DepositStatusChanged($transaction->fresh()));
        return response()->json(['transaction' => $transaction->fresh()]);
    }

    public function approveWithdrawal(Transaction $transaction): JsonResponse
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return response()->json(['message' => 'Not a withdrawal.'], 422);
        }

        if ($transaction->status === TransactionStatus::Approved) {
            return response()->json(['message' => 'Already approved.'], 422);
        }

        if ($transaction->status === TransactionStatus::Rejected) {
            return response()->json(['message' => 'Cannot approve a rejected withdrawal.'], 422);
        }

        $transaction->update(['status' => TransactionStatus::Approved]);

        $this->safeBroadcast(new WithdrawalStatusChanged($transaction->fresh()));

        return response()->json(['transaction' => $transaction->fresh()]);
    }

    public function rejectWithdrawal(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return response()->json(['message' => 'Not a withdrawal.'], 422);
        }

        if ($transaction->status !== TransactionStatus::Pending) {
            return response()->json(['message' => 'Only pending withdrawals can be rejected.'], 422);
        }

        DB::transaction(function () use ($transaction, $request) {
            $locked = Transaction::lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status !== TransactionStatus::Pending) return;
            $user = \App\Models\User::lockForUpdate()->findOrFail($locked->user_id);
            $locked->update([
                'status' => TransactionStatus::Rejected,
                'rejection_reason' => $request->string('reason')->toString() ?: null,
            ]);
            $user->increment('balance', (float) $locked->amount);
        });

        $this->safeBroadcast(new WithdrawalStatusChanged($transaction->fresh()));

        return response()->json(['transaction' => $transaction->fresh()]);
    }

    private function safeBroadcast(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed (non-fatal)', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
