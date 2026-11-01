<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedForAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'يرجى تسجيل الدخول أولاً.'], 401);
        }

        // Preserve existing staff access; customer accounts must verify email.
        if (!$user->hasAnyRole(['admin', 'moderator']) && $user->email_verified_at === null) {
            return response()->json([
                'message' => 'يرجى تأكيد البريد الإلكتروني قبل استخدام هذه الخدمة.',
                'code' => 'EMAIL_NOT_VERIFIED',
            ], 403);
        }

        return $next($request);
    }
}
