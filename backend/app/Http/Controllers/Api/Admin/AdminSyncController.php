<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class AdminSyncController extends Controller
{
    public function applyMarkup(): JsonResponse
    {
        try {
            Artisan::call('oranos:apply-markup');
            return response()->json(['ok' => true, 'output' => Artisan::output()]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    }

    public function syncProducts(): JsonResponse
    {
        try {
            Artisan::call('oranos:sync-products');
            return response()->json(['ok' => true, 'output' => Artisan::output()]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    }
}
