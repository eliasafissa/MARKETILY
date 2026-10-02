<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PartnerApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Require JSON accept header
        if (! $request->expectsJson()) {
            return response()->json(['error' => 'accept_json_required'], 406);
        }

        // Read API token from header (api-token or X-Api-Token)
        $token = $request->header('api-token') ?? $request->header('X-Api-Token');

        if (! $token) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        // Look up user by api_key
        $user = User::where('api_key', $token)->first();

        if (! $user) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        // Verify user has an approved partner API request
        $hasApprovedRequest = $user->partnerApiRequest()
            ->where('status', 'approved')
            ->exists();

        if (! $hasApprovedRequest) {
            return response()->json(['error' => 'no_approved_request'], 403);
        }

        // Log in the user for this request
        Auth::login($user);

        return $next($request);
    }
}