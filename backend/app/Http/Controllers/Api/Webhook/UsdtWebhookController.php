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
 * Dedicated USDT webhook endpoint.
 * Signature verification uses USDT_WEBHOOK_SECRET from config.
 */
class UsdtWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        Log::info('USDT webhook received', [
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        // 1. Read JSON payload
        $payload = $request->json()->all();

        // 2. Verify HMAC-SHA256 signature
        $signature = $request->header('X-Usdt-Signature');
        $secret = config('services.usdt.secret');
        $body = $request->getContent();
        $expected = hash_hmac('sha256', $body, $secret);

        if (! hash_equals($expected, (string) $signature)) {
            Log::warning('USDT webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        // 3. Check confirmation status
        if (($payload['status'] ?? null) !== 'confirmed') {
            return response()->json(['error' => 'Transaction not confirmed.'], 400);
        }

        // 4. Find transaction by reference
        $reference = $payload['reference'] ?? null;
        $transaction = Transaction::where('gateway_ref', $reference)->first();

        if (! $transaction) {
            Log::warning('USDT webhook: transaction not found', ['reference' => $reference]);

            return response()->json(['error' => 'Transaction not found.'], 404);
        }

        if ($transaction->status !== TransactionStatus::Pending) {
            return response()->json(['message' => 'Already processed.']);
        }

        // 5. Validate amount tolerance (within 0.01 USDT)
        $expectedAmount = (float) ($payload['expected_amount'] ?? 0);
        $actualAmount = (float) ($payload['amount'] ?? 0);

        if (abs($actualAmount - $expectedAmount) > 0.01) {
            Log::warning('USDT webhook: amount mismatch', [
                'expected' => $expectedAmount,
                'actual' => $actualAmount,
            ]);

            return response()->json(['error' => 'Amount mismatch.'], 400);
        }

        // 6. Credit user's wallet and mark approved
        $user = $transaction->user;
        $user->increment('balance', $transaction->amount);
        $transaction->update(['status' => TransactionStatus::Approved]);

        event(new DepositStatusChanged($transaction));

        Log::info('USDT webhook: deposit approved', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'amount' => $transaction->amount,
        ]);

        return response()->json(['message' => 'OK']);
    }
}
