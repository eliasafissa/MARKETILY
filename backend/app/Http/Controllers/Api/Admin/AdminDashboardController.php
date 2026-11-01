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
        // MARKETLLY_CUSTOMER_BALANCE_TOTAL_V1
        // Current customer wallet liabilities, excluding administrator roles.
        // This is a live SQL SUM, not a sum of historical deposits.
        $customerWalletBalance = User::query()
            ->whereDoesntHave('roles', function ($query) {
                $query->whereRaw('LOWER(name) IN (?, ?, ?, ?, ?)', [
                    'admin', 'administrator', 'super-admin', 'super_admin', 'superadmin',
                ]);
            })
            ->sum('balance');
        $totalRevenue = Order::where('status', OrderStatus::Completed)
            ->whereHas('user')->sum('total');
        $collectedPayments = Transaction::where('type', TransactionType::Purchase)
            ->where('status', TransactionStatus::Approved)->sum('amount');

        // Only submitted requests count as reviewable. Historical transactions stay in the archive.
        $visibleDeposits = Transaction::where('type', TransactionType::Deposit)
            ->where(function ($q) {
                $q->where('meta->stage', '!=', 'awaiting_transfer')
                  ->orWhereNull('meta->stage');
            });
        $pendingDeposits = (clone $visibleDeposits)
            ->where('status', TransactionStatus::Pending)->count();
        $pendingDepositAmount = (clone $visibleDeposits)
            ->where('status', TransactionStatus::Pending)->sum('amount');
        $approvedDepositAmount = (clone $visibleDeposits)
            ->where('status', TransactionStatus::Approved)->sum('amount');
        $depositHistoryCount = (clone $visibleDeposits)->count();
        $recentDeposits = (clone $visibleDeposits)->with('user:id,name,email')
            ->orderByDesc('id')->limit(8)->get()
            ->map(fn ($deposit) => [
                'id' => $deposit->id,
                'customer' => $deposit->user?->name ?? '—',
                'amount_usd' => (string) $deposit->amount,
                'transfer_amount' => $deposit->meta['transfer_amount'] ?? $deposit->amount,
                'transfer_currency' => $deposit->meta['transfer_currency'] ?? 'USD',
                'method' => $deposit->method,
                'status' => $deposit->status->value,
                'created_at' => $deposit->created_at?->toIso8601String(),
            ])->values();

        $pendingWithdrawals = Transaction::where('type', TransactionType::Withdrawal)
            ->where('status', TransactionStatus::Pending)
            ->count();

        $pendingManualOrders = Order::whereIn('status', [OrderStatus::Pending, OrderStatus::Processing])
            ->where(function ($q) {
                $q->whereHas('items.product', fn ($p) => $p->where('type', 'manual'))
                    ->orWhereNotNull('failure_reason')
                    ->orWhere('payment_method', '!=', 'cash_wallet');
            })
            ->count();

        $recentOrders = Order::with('user')->latest()->limit(10)->get();

        $vipBreakdown = User::selectRaw('vip_level, COUNT(*) as count')
            ->groupBy('vip_level')
            ->pluck('count', 'vip_level');

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'customer_wallet_balance_usd' => round((float) $customerWalletBalance, 2),
                'total_revenue' => round((float) $totalRevenue, 2),
                'collected_payments' => round((float) $collectedPayments, 2),
                'completed_orders' => Order::where('status', OrderStatus::Completed)->count(),
                'processing_orders' => Order::where('status', OrderStatus::Processing)->count(),
                'rejected_orders' => Order::where('status', OrderStatus::Rejected)->count(),
                'pending_deposits' => $pendingDeposits,
                'pending_deposits_amount_usd' => round((float) $pendingDepositAmount, 2),
                'approved_deposits_amount_usd' => round((float) $approvedDepositAmount, 2),
                'deposits_history_count' => $depositHistoryCount,
                'pending_withdrawals' => $pendingWithdrawals,
                'pending_manual_orders' => $pendingManualOrders,
                'vip_breakdown' => $vipBreakdown,
            ],
            'recent_orders' => $recentOrders,
            'recent_deposits' => $recentDeposits,
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
            return ['status' => 'error', 'message' => 'Database connection failed'];
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
            return ['status' => 'warn', 'message' => 'Storage not writable'];
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
