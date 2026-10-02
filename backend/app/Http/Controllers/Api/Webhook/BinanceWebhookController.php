<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\TransactionStatus;
use App\Events\DepositStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated Binance Pay webhook endpoint.
 * Signature verification uses BINANCE_PAY_SECRET from config.
 */
class BinanceWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        Log::info('Binance webhook received', [
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        // 1. Read JSON payload
        $payload = $request->json()->all();

        // 2. Verify HMAC-SHA512 signature
        $signature = $request->header('X-Binance-Signature');
        $secret = config('services.binance_pay.secret');
        $body = $request->getContent();
        $expected = hash_hmac('sha512', $body, $secret);

        if (! hash_equals($expected, (string) $signature)) {
            Log::warning('Binance webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        // 3. Check payment status
        if (($payload['status'] ?? null) !== 'PAID') {
            return response()->json(['error' => 'Payment not completed.'], 400);
        }

        // 4. Find transaction by reference
        $reference = $payload['reference'] ?? null;
        $transaction = Transaction::where('gateway_ref', $reference)->first();

        if (! $transaction) {
            Log::warning('Binance webhook: transaction not found', ['reference' => $reference]);

            return response()->json(['error' => 'Transaction not found.'], 404);
        }

        if ($transaction->status !== TransactionStatus::Pending) {
            return response()->json(['message' => 'Already processed.']);
        }

        // 5. Credit user's wallet and mark approved
        $user = $transaction->user;
        $user->increment('balance', $transaction->amount);
        $transaction->update(['status' => TransactionStatus::Approved]);

        event(new DepositStatusChanged($transaction));

        Log::info('Binance webhook: deposit approved', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'amount' => $transaction->amount,
        ]);

        return response()->json(['message' => 'OK']);
    }
}
