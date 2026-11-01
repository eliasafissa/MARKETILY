<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OranosMarketService;
use App\Services\OrderService;
use Illuminate\Console\Command;

class PollOranosOrders extends Command
{
    protected $signature = 'orders:poll-oranos';
    protected $description = 'Poll each submitted Oranos order item without resending purchase requests';

    public function handle(OranosMarketService $oranos, OrderService $orders): int
    {
        $items = OrderItem::query()->whereNotNull('oranos_order_id')
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Processing))
            ->with('order')->limit(500)->get();
        if ($items->isEmpty()) return self::SUCCESS;

        foreach ($items->chunk(50) as $chunk) {
            try {
                $response = $oranos->checkOrders($chunk->pluck('oranos_order_id')->all());
            } catch (\Throwable $e) {
                $this->error('Oranos check failed (no changes): ' . $e->getMessage());
                return self::FAILURE;
            }
            $byId = collect($response['data'] ?? [])->keyBy('order_id');
            foreach ($chunk as $item) {
                $status = $byId->get($item->oranos_order_id)['status'] ?? null;
                if (in_array($status, ['accept', 'reject', 'wait'], true)) {
                    $item->update(['oranos_status' => $status]);
                }
            }
        }
        foreach ($items->pluck('order_id')->unique() as $id) {
            $orders->reconcileOrder(Order::findOrFail($id));
        }
        $this->info('Checked ' . $items->count() . ' Oranos order item(s).');
        return self::SUCCESS;
    }
}
