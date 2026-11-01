<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotifyManualDeposit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 15;
    public array $backoff = [10, 60, 180];

    public function __construct(public readonly int $transactionId) {}

    public function handle(): void
    {
        $token = (string) config('services.admin_telegram.bot_token', '');
        $chatId = (string) config('services.admin_telegram.chat_id', '');
        if ($token === '' || $chatId === '') return;

        $transaction = Transaction::find($this->transactionId);
        if (!$transaction || $transaction->type->value !== 'deposit' || ($transaction->meta['stage'] ?? null) !== 'awaiting_admin') return;

        $url = rtrim((string) config('services.frontend_url'), '/') . '/admin/deposits';
        $text = "🧾 Marketlly - New manual deposit\n"
            . "Order #{$transaction->id}\n"
            . "Method: {$transaction->method}\n"
            . "Credit: {$transaction->amount} USD\n"
            . "Please verify transfer inside payment provider before approving.\n{$url}";

        $response = Http::timeout(8)->post('https://api.telegram.org/bot' . $token . '/sendMessage', [
            'chat_id' => $chatId, 'text' => $text,
            'disable_web_page_preview' => true,
        ]);
        if (!$response->successful() || $response->json('ok') !== true) {
            Log::warning('Admin deposit Telegram notification failed', ['transaction_id' => $transaction->id, 'http_status' => $response->status()]);
            throw new \RuntimeException('Telegram delivery unsuccessful');
        }
    }
}
