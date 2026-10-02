<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// ─────────────────────────────────────────────────────────
// TEMPORARY: Maintenance endpoint for Oranos sync
// Protected by a secret key in the URL.
// ⚠️ REMOVE AFTER FIRST SYNC IS DONE.
// ─────────────────────────────────────────────────────────
Route::get('/__maintenance/sync-oranos/{secret}', function (string $secret) {
    $expectedSecret = env('SYNC_SECRET', 'change-me-' . date('Ymd'));
    if ($secret !== $expectedSecret) {
        abort(404);
    }

    @set_time_limit(600);
    @ini_set('memory_limit', '512M');

    $output = [];
    $output[] = '=== Starting Fresh Sync ===';
    $output[] = 'Timestamp: ' . now()->toIso8601String();

    try {
        Artisan::call('oranos:fresh-sync', ['--force' => true]);
        $output[] = Artisan::output();
    } catch (\Throwable $e) {
        $output[] = '❌ ERROR: ' . $e->getMessage();
        $output[] = $e->getTraceAsString();
    }

    return response('<pre>' . e(implode("\n", $output)) . '</pre>');
});
