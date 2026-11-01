<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReset as PasswordResetMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));
        // Limit each requested email address, whether registered or not, to
        // one reset-link request per 30 seconds. No address enumeration.
        $key = 'marketlly-reset-resend:' . hash('sha256', $email);
        $until = (int) Cache::get($key, 0);
        $remaining = max(0, $until - time());
        if ($remaining > 0 || !Cache::add($key, time() + 30, 30)) {
            $remaining = $remaining > 0 ? $remaining : max(1, (int) Cache::get($key, time() + 30) - time());
            return response()->json([
                'message' => 'يرجى الانتظار قبل طلب رابط جديد.',
                'retry_after' => $remaining,
            ], 429)->header('Retry-After', (string) $remaining);
        }
        $user = User::where('email', $email)->first();

        if ($user) {
            // Laravel broker hashes reset tokens in the database and applies expiry.
            $token = Password::broker()->createToken($user);
            $front = rtrim((string) config('services.frontend_url'), '/');
            $url = $front.'/reset-password?token='.urlencode($token).'&email='.urlencode($email);
            try {
                Mail::to($user->email)->send(new PasswordResetMail($user->name, $url, $token));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Always return an identical message to avoid revealing registered addresses.
        return response()->json(['message' => 'إذا كان البريد الإلكتروني مرتبطاً بحساب، سيصل رابط الاستعادة.', 'retry_after' => 30]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete(); // revoke existing API sessions after reset
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password updated. Please log in again.'])
            : response()->json(['message' => 'Reset link invalid or expired.'], 422);
    }
}
