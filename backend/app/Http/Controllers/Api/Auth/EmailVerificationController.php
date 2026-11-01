<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public static function mailReady(): bool
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['', 'log', 'array'], true)) return false;
        if ($mailer === 'smtp' && (trim((string) config('mail.mailers.smtp.host')) === '' ||
            trim((string) config('mail.from.address')) === '')) return false;
        return true;
    }

    public static function deliver(User $user): void
    {
        if (!self::mailReady()) throw new \RuntimeException('SMTP_NOT_CONFIGURED');
        if (!Schema::hasColumn('users', 'email_verified_at')) throw new \RuntimeException('EMAIL_VERIFICATION_COLUMN_MISSING');
        if ($user->email_verified_at) return;
        $url = URL::temporarySignedRoute('haidar.email.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1(mb_strtolower((string) $user->email)),
        ]);
        Mail::raw("لتأكيد بريد حسابك في Marketlly افتح الرابط خلال 60 دقيقة:\n".$url."\n\nإذا لم تنشئ هذا الحساب فتجاهل الرسالة.",
            function ($msg) use ($user) { $msg->to($user->email)->subject('تأكيد البريد الإلكتروني — Marketlly'); });
        // A successful delivery (including registration) starts the 30-second cooldown.
        Cache::put('marketlly-email-resend:' . $user->getKey(), time() + 30, 30);
    }

    public function send(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) return response()->json(['message' => 'البريد الإلكتروني مؤكَّد بالفعل.']);
        if (!self::mailReady()) return response()->json(['message' => 'البريد غير مهيأ للإرسال الحقيقي. اضبط SMTP أولاً.'], 503);
        $key = 'marketlly-email-resend:' . $user->getKey();
        $until = (int) Cache::get($key, 0);
        $remaining = max(0, $until - time());
        if ($remaining > 0) {
            return response()->json([
                'message' => 'يرجى الانتظار قبل إعادة إرسال رابط التأكيد.',
                'retry_after' => $remaining,
            ], 429)->header('Retry-After', (string) $remaining);
        }
        if (!Cache::add($key, time() + 30, 30)) {
            $remaining = max(1, (int) Cache::get($key, time() + 30) - time());
            return response()->json([
                'message' => 'يرجى الانتظار قبل إعادة إرسال رابط التأكيد.',
                'retry_after' => $remaining,
            ], 429)->header('Retry-After', (string) $remaining);
        }
        try { self::deliver($user); }
        catch (\Throwable $e) {
            Cache::forget($key);
            report($e);
            return response()->json(['message' => 'تعذر إرسال رسالة التأكيد. حاول لاحقاً.'], 503);
        }
        return response()->json(['message'=>'أُرسلت رسالة التأكيد إلى بريدك.', 'retry_after' => 30]);
    }

    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1(mb_strtolower((string) $user->email)), $hash), 403);
        abort_unless(Schema::hasColumn('users', 'email_verified_at'), 503);
        if (!$user->email_verified_at) $user->forceFill(['email_verified_at' => now()])->save();
        $front = rtrim((string) config('services.frontend_url', 'http://localhost:5173'), '/');
        return redirect()->away($front.'/login?email_verified=1');
    }
}
