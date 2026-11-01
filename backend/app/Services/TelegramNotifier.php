<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public static function sendDepositAlert($transaction, $user, $data, $receiptPath)
    {
        Log::info('?? TelegramNotifier: Attempting to send alert for transaction #' . $transaction->id);
        
        $token = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_ADMIN_CHAT_ID');

        if (!$token || !$chatId) {
            Log::warning('?? TelegramNotifier: Token or ChatID missing in config/env');
            return;
        }

        $text = "?? *??? ????? ???? ??????? ????????!*\n\n";
        $text .= "??? ???????: #" . $transaction->id . "\n";
        $text .= "??????: " . ($user?->name ?? '????') . "\n";
        $text .= "??????: " . $data['amount'] . " " . $data['currency'] . "\n";
        $text .= "???????: " . $data['method'] . "\n";

        try {
            $photoPath = Storage::disk('local')->path($receiptPath);
            
            if (file_exists($photoPath)) {
                Log::info('? TelegramNotifier: Photo exists, sending sendPhoto');
                $response = Http::timeout(10)->attach(
                    'photo', file_get_contents($photoPath), basename($photoPath)
                )->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                    'chat_id' => $chatId,
                    'caption' => $text,
                    'parse_mode' => 'Markdown'
                ]);
            } else {
                Log::info('? TelegramNotifier: Photo missing, sending text only');
                $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown'
                ]);
            }

            Log::info('?? TelegramNotifier: API Response', ['status' => $response->status(), 'body' => $response->body()]);
            
        } catch (\Throwable $e) {
            Log::error('?? TelegramNotifier Error: ' . $e->getMessage());
        }
    }
}
