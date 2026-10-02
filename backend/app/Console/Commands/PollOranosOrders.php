<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OranosMarketService;
use App\Services\OrderService;
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
        $rejected = 0;

        foreach ($orders as $order) {
            $row = $byId[(string) $order->oranos_order_id] ?? null;
            if (! $row) {
                continue;
            }

            // Oranos returns: accept / reject / wait
            $status = strtolower((string) ($row['status'] ?? $row['state'] ?? ''));
            $order->update(['oranos_status' => $status]);

            if ($status === 'accept') {
                $order->update(['status' => OrderStatus::Completed]);
                $completed++;
                $this->line("Order {$order->id} -> completed");
            } elseif ($status === 'reject') {
                $svc = app(OrderService::class);
                $ref = new \ReflectionMethod($svc, 'refundFailedOrder');
                $ref->setAccessible(true);
                $ref->invoke($svc, $order, 'Oranos rejected: ' . $status);
                $rejected++;
                $this->line("Order {$order->id} -> rejected by Oranos, refunded");
            }
            // 'wait' -> leave as Processing
        }

        $this->info("Completed {$completed}, Rejected {$rejected}.");
        return self::SUCCESS;
    }
}