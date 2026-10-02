<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\OranosMarketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminOranosController extends Controller
{
    public function __construct(private readonly OranosMarketService $oranos) {}

    public function balance(): JsonResponse
    {
        try {
            $profile = $this->oranos->getProfile();
        } catch (\Throwable $e) {
            Log::warning('Oranos profile fetch failed', ['error' => $e->getMessage()]);

            return response()->json([
                'ok'         => false,
                'balance'    => 0,
                'email'      => null,
                'two_factor' => false,
                'level'      => 'critical',
                'message'    => 'Could not reach Oranos: ' . $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ]);
        }

        $balance = (float) ($profile['balance'] ?? 0);

        $level = match (true) {
            $balance >= 50  => 'healthy',
            $balance >= 20  => 'watch',
            $balance >= 10  => 'low',
            default         => 'critical',
        };

        return response()->json([
            'ok'         => true,
            'balance'    => $balance,
            'email'      => $profile['email'] ?? null,
            'two_factor' => (bool) ($profile['towFactor'] ?? false),
            'level'      => $level,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function refresh(): JsonResponse
    {
        Cache::forget('oranos.profile');
        Cache::forget('oranos.profile.at');

        return $this->balance();
    }
}
