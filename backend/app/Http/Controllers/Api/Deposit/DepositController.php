<?php

namespace App\Http\Controllers\Api\Deposit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositCurrency;
use App\Services\PaymentGatewayManager;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Jobs\NotifyManualDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\TelegramNotifier;

class DepositController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(Transaction::where('user_id', $request->user()->id)
            ->where('type', TransactionType::Deposit)
            ->where(function ($q) {
                $q->where('meta->stage', '!=', 'awaiting_transfer')
                  ->orWhereNull('meta->stage');
            })->latest()->paginate(20));
    }

    public function preview(Request $request, DepositCurrency $currency): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'currency' => ['required', 'in:USD,SYP'],
            'method' => ['required', 'in:sham_cash,sham_cash_usd,syriatel_cash,c_wallet,binance_pay,usdt'],
        ]);
        try {
            $syrian = in_array($data['method'], ['sham_cash', 'syriatel_cash'], true);
            $requiredCurrency = $syrian ? 'SYP' : 'USD';
            if ($data['currency'] !== $requiredCurrency) {
                throw new \DomainException('هذه الطريقة تتطلب إدخال المبلغ بعملة ' . $requiredCurrency . '.');
            }
            if ($syrian && floor((float) $data['amount']) !== (float) $data['amount']) {
                throw new \DomainException('مبلغ الليرة السورية يجب أن يكون رقماً صحيحاً.');
            }
            $quote = $currency->quote((float) $data['amount'], $data['currency'], $data['method']);
            $amount = $quote['amount_usd'];
            if ($amount < 0.01 || $amount > 1000000) throw new \DomainException('USD wallet amount out of range.');
            $instructions = $this->gateways->driver($data['method'])->createDeposit($amount, 'USD');
            if ($syrian) $instructions['transfer_amount'] = (float) $data['amount'];
            return response()->json([
                'deposit' => $instructions,
                'amount_usd' => $amount,
                'input_amount' => (float) $data['amount'],
                'input_currency' => $data['currency'],
                'status' => 'preview_only',
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request, DepositCurrency $currency): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'currency' => ['required', 'in:USD,SYP'],
            'method' => ['required', 'in:sham_cash,sham_cash_usd,syriatel_cash,c_wallet,binance_pay,usdt'],
            'payment_reference' => ['required', 'string', 'max:190', $request->input('method') === 'usdt' ? 'regex:/^0x[a-fA-F0-9]{64}$/' : 'min:1'],
            'receipt' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'receiver_confirmed' => ['required', 'accepted'],
            'expected_receiver' => ['required', 'string', 'max:250'],
            'expected_transfer_amount' => ['required', 'numeric', 'gt:0'],
            'expected_transfer_currency' => ['required', 'in:USD,SYP'],
        ]);
        $ref = mb_strtolower(trim($data['payment_reference']));
        if ($ref === '') return response()->json(['message' => 'رقم العملية مطلوب.'], 422);
        try {
            $syrian = in_array($data['method'], ['sham_cash', 'syriatel_cash'], true);
            $requiredCurrency = $syrian ? 'SYP' : 'USD';
            if ($data['currency'] !== $requiredCurrency) {
                throw new \DomainException('هذه الطريقة تتطلب إدخال المبلغ بعملة ' . $requiredCurrency . '.');
            }
            if ($syrian && floor((float) $data['amount']) !== (float) $data['amount']) {
                throw new \DomainException('مبلغ الليرة السورية يجب أن يكون رقماً صحيحاً.');
            }
            $quote = $currency->quote((float) $data['amount'], $data['currency'], $data['method']);
            $amount = $quote['amount_usd'];
            if ($amount < 0.01 || $amount > 1000000) throw new \DomainException('USD wallet amount out of range.');
            $instructions = $this->gateways->driver($data['method'])->createDeposit($amount, 'USD');
            if ($syrian) $instructions['transfer_amount'] = (float) $data['amount'];
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if (strtolower(trim($data['expected_receiver'])) !== strtolower($instructions['wallet_address']) ||
            $data['expected_transfer_currency'] !== $instructions['transfer_currency'] ||
            abs((float) $data['expected_transfer_amount'] - (float) $instructions['transfer_amount']) > 0.000001) {
            return response()->json(['message' => 'تغيّرت بيانات التحويل؛ يرجى مراجعة تفاصيل الإيداع مجدداً قبل إرسال الإثبات.'], 409);
        }
        $receiptFile = $request->file('receipt');
        $fingerprint = hash_file('sha256', $receiptFile->getRealPath());
        $receiptPath = $receiptFile->store('deposit-receipts', 'local');
        if (!$receiptPath) return response()->json(['message' => 'تعذّر حفظ الإيصال، لم يتم إنشاء إيداع.'], 500);
        try {
            $transaction = DB::transaction(function () use ($request, $data, $amount, $instructions, $ref, $fingerprint, $receiptPath) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $pending = Transaction::where('user_id', $request->user()->id)
                    ->where('type', TransactionType::Deposit)
                    ->where('status', TransactionStatus::Pending)
                    ->where('meta->stage', 'awaiting_admin')->count();
                if ($pending >= 5) throw new \DomainException('Maximum five submitted deposits awaiting review per account.');
                $meta = $instructions;
                $meta['input_currency'] = $data['currency'];
                $meta['input_amount'] = (float) $data['amount'];
                $meta['credited_amount_usd'] = $amount;
                $meta['transfer_amount'] = $instructions['transfer_amount'];
                $meta['transfer_currency'] = $instructions['transfer_currency'];
                $meta['stage'] = 'awaiting_admin';
                $meta['receipt_path'] = $receiptPath;
                $meta['receipt_sha256'] = $fingerprint;
                $meta['payment_reference'] = $ref;
                $meta['verification'] = 'manual_admin_reconciliation';
                $meta['submitted_at'] = now()->toIso8601String();
                return Transaction::create([
                    'user_id' => $request->user()->id, 'type' => TransactionType::Deposit,
                    'amount' => $amount, 'fee' => 0, 'status' => TransactionStatus::Pending,
                    'method' => $data['method'], 'gateway_ref' => $instructions['reference'],
                    'payment_reference' => $ref, 'meta' => $meta,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            Storage::disk('local')->delete($receiptPath);
            return response()->json(['message' => 'تم تقديم رقم العملية هذا من قبل؛ لم يُنشأ إيداع جديد.'], 409);
        } catch (\DomainException $e) {
            Storage::disk('local')->delete($receiptPath);
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($receiptPath);
            throw $e;
        }
        
        try { NotifyManualDeposit::dispatch((int) $transaction->id)->afterCommit(); }
        catch (\Throwable $e) { report($e); }

        

        // Direct Telegram Notification (Using config instead of env)
        try {
            file_put_contents('/tmp/deposit_debug.txt', "\n[TG BLOCK START] Transaction ID: " . $transaction->id, FILE_APPEND);
            
            $token = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
            $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_ADMIN_CHAT_ID');
            
            file_put_contents('/tmp/deposit_debug.txt', "\nToken: " . ($token ? 'OK' : 'MISSING') . " | ChatID: " . ($chatId ? 'OK' : 'MISSING'), FILE_APPEND);
            
            if ($token && $chatId) {
                $text = "💰 *طلب إيداع جديد بانتظار الموافقة!*\n\n";
                $text .= "رقم العملية: #" . $transaction->id . "\n";
                $text .= "العميل: " . ($request->user()?->name ?? 'زائر') . "\n";
                $text .= "المبلغ: " . $data['amount'] . " " . $data['currency'] . "\n";
                $text .= "الطريقة: " . $data['method'];
                
                $photoPath = Storage::disk('local')->path($receiptPath);
                file_put_contents('/tmp/deposit_debug.txt', "\nPhoto Path: " . $photoPath . " | Exists: " . (file_exists($photoPath) ? 'YES' : 'NO'), FILE_APPEND);
                
                if (file_exists($photoPath)) {
                    $response = \Illuminate\Support\Facades\Http::timeout(15)->attach(
                        'photo', file_get_contents($photoPath), basename($photoPath)
                    )->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                        'chat_id' => $chatId,
                        'caption' => $text,
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    $response = \Illuminate\Support\Facades\Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $text . "\n(لم يتم العثور على صورة الإيصال)",
                        'parse_mode' => 'Markdown'
                    ]);
                }
                file_put_contents('/tmp/deposit_debug.txt', "\nTG Response: " . $response->body(), FILE_APPEND);
            }
        } catch (\Throwable $e) {
            file_put_contents('/tmp/deposit_debug.txt', "\nTG Error: " . $e->getMessage(), FILE_APPEND);
        }

        return response()->json([
            'transaction' => $transaction,
            'balance' => (string) $request->user()->fresh()->balance,
            'verification' => 'pending_admin_review',
        ], 201);
    }

    public function confirm(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->type !== TransactionType::Deposit || (int) $transaction->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Deposit not found.'], 404);
        }
        if ($transaction->status !== TransactionStatus::Pending || ($transaction->meta['stage'] ?? null) !== 'awaiting_transfer') {
            return response()->json(['message' => 'Deposit already processed or receipt already submitted.'], 409);
        }

        $isUSDT = $transaction->method === 'usdt';
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:190', $isUSDT ? 'regex:/^0x[a-fA-F0-9]{64}$/' : 'min:1'],
            'receipt' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);
        $ref = mb_strtolower(trim($data['payment_reference']));
        if ($ref === '') {
            return response()->json(['message' => 'رقم العملية مطلوب.'], 422);
        }
        $sender = '';
        $referenceSupplied = true;
        $fingerprint = hash_file('sha256', $request->file('receipt')->getRealPath());
        $receiptPath = $request->file('receipt')->store('deposit-receipts', 'local');
        try {
            $result = DB::transaction(function () use ($transaction, $ref, $sender, $data, $receiptPath, $referenceSupplied, $fingerprint) {
                $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                if ($locked->type !== TransactionType::Deposit || $locked->status !== TransactionStatus::Pending || ($locked->meta['stage'] ?? null) !== 'awaiting_transfer') {
                    throw new \DomainException('Receipt already submitted or deposit processed.');
                }
                $meta = $locked->meta ?? [];
                $meta['payment_reference'] = $ref;
                $meta['reference_is_internal'] = !$referenceSupplied;
                $meta['receipt_sha256'] = $fingerprint;
                $meta['sender_account'] = $sender;
                $meta['sender_name'] = '';
                $meta['submitted_at'] = now()->toIso8601String();
                $meta['stage'] = 'awaiting_admin';
                $meta['verification'] = 'manual_admin_reconciliation';
                $meta['receipt_path'] = $receiptPath;
                $locked->update(['payment_reference' => $ref, 'meta' => $meta]);
                return $locked->fresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            Storage::disk('local')->delete($receiptPath);
            return response()->json(['message' => 'This transfer reference has already been claimed.'], 409);
        } catch (\DomainException $e) {
            Storage::disk('local')->delete($receiptPath);
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($receiptPath);
            throw $e;
        }

        try {
            NotifyManualDeposit::dispatch((int) $result->id)->afterCommit();
        } catch (\Throwable $e) {
            report($e);
        }

        
        return response()->json([
            'transaction' => $result,
            'balance' => (string) $request->user()->fresh()->balance,
            'verification' => 'pending_admin_review',
        ]);
    }

    public function cancel(Request $request, Transaction $transaction): JsonResponse
    {
        if ((int) $transaction->user_id !== (int) $request->user()->id || $transaction->type !== TransactionType::Deposit) {
            return response()->json(['message' => 'Deposit not found.'], 404);
        }
        $cancelled = DB::transaction(function () use ($transaction) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== TransactionStatus::Pending || ($locked->meta['stage'] ?? null) !== 'awaiting_transfer') return false;
            $locked->update(['status' => TransactionStatus::Rejected, 'rejection_reason' => 'Cancelled before submitting transfer']);
            return true;
        });
        return response()->json(['cancelled' => $cancelled], $cancelled ? 200 : 409);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if ((int) $transaction->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return response()->json([
            'transaction' => $transaction,
        ]);
    }
}