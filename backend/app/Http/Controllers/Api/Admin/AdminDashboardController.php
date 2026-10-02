<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $totalUsers = User::count();
        $totalRevenue = Transaction::where('type', TransactionType::Purchase)
            ->where('status', TransactionStatus::Approved)
            ->sum('amount');

        $pendingDeposits = Transaction::where('type', TransactionType::Deposit)
            ->where('status', TransactionStatus::Pending)
            ->count();

        $pendingWithdrawals = Transaction::where('type', TransactionType::Withdrawal)
            ->where('status', TransactionStatus::Pending)
            ->count();

        $pendingManualOrders = Order::whereIn('status', [OrderStatus::Pending, OrderStatus::Processing])
            ->whereHas('items.product', fn($q) => $q->where('type', 'manual'))
            ->count();

        $recentOrders = Order::with('user')->latest()->limit(10)->get();

        $vipBreakdown = User::selectRaw('vip_level, COUNT(*) as count')
            ->groupBy('vip_level')
            ->pluck('count', 'vip_level');

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'total_revenue' => round($totalRevenue, 2),
                'pending_deposits' => $pendingDeposits,
                'pending_withdrawals' => $pendingWithdrawals,
                'pending_manual_orders' => $pendingManualOrders,
                'vip_breakdown' => $vipBreakdown,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * System health check — verifies DB connectivity, storage, and Reverb config.
     */
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'reverb' => $this->checkReverb(),
        ];

        $allHealthy = collect($checks)->every(fn($c) => $c['status'] === 'ok');

        return response()->json([
            'healthy' => $allHealthy,
            'checks' => $checks,
            'app_debug' => config('app.debug'),
            'app_env' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = config('filesystems.default', 'local');
            $path = $disk === 'public' ? 'public/test-health.txt' : 'test-health.txt';
            Storage::put($path, 'ok', 'public');
            Storage::delete($path);
            return ['status' => 'ok', 'message' => 'Writable — disk: ' . $disk];
        } catch (\Throwable $e) {
            return ['status' => 'warn', 'message' => 'Storage not writable: ' . $e->getMessage()];
        }
    }

    private function checkReverb(): array
    {
        $host = config('broadcasting.connections.reverb.host');
        $port = config('broadcasting.connections.reverb.port');
        $key = config('broadcasting.connections.reverb.key');

        if (empty($key)) {
            return ['status' => 'warn', 'message' => 'Reverb not configured (no app key)'];
        }

        return [
            'status' => 'ok',
            'message' => "Configured — {$host}:{$port}",
        ];
    }
}
