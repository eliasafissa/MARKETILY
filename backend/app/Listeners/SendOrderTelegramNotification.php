<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Http;

class SendOrderTelegramNotification
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        if (!$order || !$order->user) return;

        $token = env('TELEGRAM_BOT_TOKEN');
        $adminId = env('TELEGRAM_ADMIN_CHAT_ID');
        if (!$token) return;

        $createdAt = \Carbon\Carbon::parse($order->created_at);
        $updatedAt = \Carbon\Carbon::parse($order->updated_at);
        $duration = $createdAt->diffForHumans($updatedAt, true); 

        $email = $order->user->email;
        $telegramId = null;
        if (str_starts_with($email, 'tg') && str_ends_with($email, '@marketlly.store')) {
            $telegramId = str_replace(['tg', '@marketlly.store'], '', $email);
        }

        if ($telegramId && in_array($order->status, ['completed', 'processing'])) {
            $statusArabic = $order->status === 'completed' ? 'تم تنفيذ طلبك بنجاح 🎉' : 'جاري تنفيذ طلبك ⏳';
            $msgClient = "مرحباً {$order->user->name}،\n\n";
            $msgClient .= "{$statusArabic}\n";
            $msgClient .= "🔖 رقم الطلب: #{$order->id}\n";
            $msgClient .= "⏳ زمن التنفيذ: {$duration}\n\n";
            $msgClient .= "شكراً لتسوقك من Marketlly 🛒";
            
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $telegramId,
                'text' => $msgClient
            ]);
        }

        if ($adminId && in_array($order->status, ['completed', 'processing'])) {
            $statusText = $order->status === 'completed' ? 'مكتمل' : 'قيد التنفيذ';
            $msgAdmin = "✅ *تحديث حالة الطلب #{$order->id}*\n\n";
            $msgAdmin .= "👤 العميل: {$order->user->name}\n";
            $msgAdmin .= "📦 الحالة الجديدة: {$statusText}\n";
            $msgAdmin .= "⏳ زمن التنفيذ: {$duration}\n";
            
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $adminId,
                'text' => $msgAdmin,
                'parse_mode' => 'Markdown'
            ]);
        }
    }
}