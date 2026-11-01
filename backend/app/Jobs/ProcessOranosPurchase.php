<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Never blindly retry an external charge: an uncertain response needs manual UUID reconciliation. */
class ProcessOranosPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 75;

    public function __construct(public readonly int $orderId) {}

    public function handle(OrderService $orders): void
    {
        $order = Order::find($this->orderId);
        if (!$order || $order->status !== OrderStatus::Processing) return;
        $orders->processAutomationOrder($order);
    }
}
