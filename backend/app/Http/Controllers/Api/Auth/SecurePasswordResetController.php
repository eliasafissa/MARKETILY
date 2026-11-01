<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class SecurePasswordResetController extends Controller
{
    public function request(Request $request)
    {
        if (!EmailVerificationController::mailReady()) {
            return response()->json(['message' => 'خدمة البريد غير مهيأة للإرسال الحقيقي. يرجى التواصل مع الإدارة.'],503);
        }
        return app(ForgotPasswordController::class)->sendResetLink($request);
    }
    public function reset(Request $request)
    {
        $request->validate(['password'=> ['required','confirmed',Password::min(10)->mixedCase()->numbers()]]);
        return app(ForgotPasswordController::class)->resetPassword($request);
    }
}
