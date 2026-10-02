<?php

namespace App\Http\Controllers\Api\Withdrawal;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Models\Transaction;
use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    public function __construct(private readonly VipService $vip)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $withdrawals = Transaction::where('user_id', $request->user()->id)
            ->where('type', TransactionType::Withdrawal)
            ->latest()
            ->paginate(20);

        return response()->json($withdrawals);
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $user   = $request->user();
        $amount = (float) $request->float('amount');

        $quote = $this->vip->applyWithdrawal($amount, $user);

        if (! $quote['allowed']) {
            return response()->json(['message' => $quote['reason']], 422);
        }

        if ((float) $user->balance < $quote['amount']) {
            return response()->json(['message' => 'Insufficient balance.'], 422);
        }

        $transaction = DB::transaction(function () use ($user, $quote, $request) {
            $user->decrement('balance', $quote['amount']);

            return Transaction::create([
                'user_id' => $user->id,
                'type'    => TransactionType::Withdrawal,
                'amount'  => $quote['amount'],
                'fee'     => $quote['fee'],
                'status'  => TransactionStatus::Pending,
                'method'  => $request->string('method'),
                'meta'    => [
                    'wallet_address' => $request->string('wallet_address'),
                    'net'            => $quote['net'],
                ],
            ]);
        });

        return response()->json([
            'transaction' => $transaction->fresh(),
            'balance'     => (float) $user->fresh()->balance,
        ], 201);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['transaction' => $transaction]);
    }
}
