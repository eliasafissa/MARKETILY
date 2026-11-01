<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Read-only inbound Binance Pay history; never authorizes transfers, trading or withdrawals. */
class BinancePayHistoryVerifier
{
    public function configured(): bool
    {
        return (bool) config('services.binance_pay.auto_verify')
            && (string) config('services.binance_pay.history_key') !== ''
            && (string) config('services.binance_pay.history_secret') !== '';
    }

    public function verify(Transaction $deposit, string $transferId, string $claimedSenderUid): array
    {
        if (!$this->configured()) throw new \DomainException('Binance Pay history verification not enabled.');
        $linked = DB::table('verified_binance_senders')->where('user_id', $deposit->user_id)->first();
        if (!$linked || !hash_equals((string) $linked->binance_uid, $claimedSenderUid)) {
            throw new \DomainException('Binance sender UID is not independently verified for this website account.');
        }
        $merchantUid = (string) config('services.manual_payments.binance_pay');
        $params = [
            'timestamp' => (string) ((int) floor(microtime(true) * 1000)),
            'recvWindow' => '5000',
            'startTime' => (string) max(0, ((int) $deposit->created_at->timestamp - 120) * 1000),
            'endTime' => (string) ((int) floor(microtime(true) * 1000)),
            'limit' => '100',
        ];
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signature = hash_hmac('sha256', $query, (string) config('services.binance_pay.history_secret'));
        $response = Http::withHeaders(['X-MBX-APIKEY' => (string) config('services.binance_pay.history_key')])
            ->timeout(12)->connectTimeout(4)
            ->get('https://api.binance.com/sapi/v1/pay/transactions?'.$query.'&signature='.$signature);
        $payload = $response->json();
        if (!$response->successful() || !is_array($payload) || ($payload['success'] ?? false) !== true) {
            throw new \RuntimeException('Binance did not confirm read-only Pay history access.');
        }
        $records = $payload['data'] ?? [];
        if (!is_array($records)) throw new \RuntimeException('Unexpected Binance Pay history response.');
        foreach ($records as $payment) {
            if (!is_array($payment) || (string) ($payment['transactionId'] ?? '') !== $transferId) continue;
            if (strtoupper((string) ($payment['currency'] ?? '')) !== 'USDT'
                || strtoupper((string) ($payment['orderType'] ?? '')) !== 'PAY'
                || (string) ($payment['receiverInfo']['binanceId'] ?? '') !== $merchantUid
                || (string) ($payment['payerInfo']['binanceId'] ?? '') !== $claimedSenderUid) {
                throw new \DomainException('Binance payment currency, type, receiver or sender UID mismatch.');
            }
            // Positive amount indicates merchant received funds; match decimal cents without accepting excess/partial amounts.
            $rawAmount = (string) ($payment['amount'] ?? '');
            if (!preg_match('/^\+?(?:\d+)(?:\.\d{1,8})?$/', $rawAmount)) {
                throw new \DomainException('Binance payment is not a positive inbound transfer.');
            }
            $paidCents = (int) round((float) $rawAmount * 100);
            $neededCents = (int) round((float) $deposit->amount * 100);
            if ($neededCents <= 0 || $paidCents !== $neededCents || abs((float) $rawAmount - $neededCents / 100) > 0.00000001) {
                throw new \DomainException('Binance payment received amount is not the exact requested amount.');
            }
            $executedMs = (int) ($payment['transactionTime'] ?? 0);
            if ($executedMs < (((int) $deposit->created_at->timestamp - 120) * 1000)) {
                throw new \DomainException('Binance payment predates the deposit request.');
            }
            return ['transaction_id' => $transferId, 'sender_uid' => $claimedSenderUid,
                'merchant_uid' => $merchantUid, 'transaction_time' => $executedMs,
                'verified_via' => 'binance_readonly_pay_history'];
        }
        throw new \DomainException('Transfer was not found in merchant Binance Pay history (or exceeded the history window).');
    }
}
