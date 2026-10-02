<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;

class PollOranosOrders extends Command
{
    protected $signature = 'orders:poll-oranos';
    protected $description = 'Check processing orders against Oranos and complete delivered ones';

    public function handle(OranosMarketService $oranos): int
    {
        $orders = Order::where('status', OrderStatus::Processing)
            ->whereNotNull('oranos_order_id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No processing orders with Oranos ids.');
            return self::SUCCESS;
        }

        $ids = $orders->pluck('oranos_order_id')->unique()->values()->all();
        $this->info('Checking ' . count($ids) . ' Oranos order(s)...');

        try {
            $response = $oranos->checkOrders($ids);
        } catch (\Throwable $e) {
            $this->error('checkOrders failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $byId = [];
        foreach (($response['data'] ?? []) as $row) {
            $id = $row['order_id'] ?? $row['id'] ?? null;
            if ($id !== null) {
                $byId[(string) $id] = $row;
            }
        }

        $completed = 0;
        foreach ($orders as $order) {
            $row = $byId[(string) $order->oranos_order_id] ?? null;
            if (! $row) {
                continue;
            }
            $status = strtolower((string) ($row['status'] ?? $row['state'] ?? ''));
            $order->update(['oranos_status' => $status]);
            if (in_array($status, ['completed', 'delivered', 'success'], true)) {
                $order->update(['status' => OrderStatus::Completed]);
                $completed++;
                $this->line("Order {$order->id} -> completed");
            } elseif (in_array($status, ['reject', 'rejected', 'failed', 'cancel', 'cancelled', 'canceled'], true)) {
                $svc = app(\App\Services\OrderService::class);
                $ref = new \ReflectionMethod($svc, 'refundFailedOrder');
                $ref->setAccessible(true);
                $ref->invoke($svc, $order, 'Oranos rejected: ' . $status);
                $this->line("Order {$order->id} -> rejected by Oranos, refunded");
            }
        }

        $this->info("Completed {$completed} order(s).");
        return self::SUCCESS;
    }
}
