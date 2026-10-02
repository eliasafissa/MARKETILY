<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Send a password reset link to the given user.
     * Returns 200 even if the email is not found (security best practice).
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $email = $request->string('email')->toString();

        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => $token,
                    'created_at' => now(),
                ]
            );

            $resetUrl = config('app.url').'/reset-password?token='.$token.'&email='.urlencode($email);

            try {
                Mail::to($user->email)->send(new PasswordReset(
                    name: $user->name,
                    resetUrl: $resetUrl,
                    token: $token,
                ));
            } catch (\Throwable $e) {
                // Non-fatal — log and continue so we don't reveal email existence
                Log::warning('Password reset email failed', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
    }
}
