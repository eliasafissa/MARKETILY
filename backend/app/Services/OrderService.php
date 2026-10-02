<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, payload?: array<string, mixed>}>  $items
     * @param  array<string, mixed>  $meta  user-supplied payment metadata (Binance ID, USDT address, etc.)
     */
    public function createOrder(User $user, array $items, string $paymentMethod = 'cash_wallet', array $meta = [], ?int $storeId = null): Order
    {
        $order = DB::transaction(function () use ($user, $items, $paymentMethod, $meta, $storeId) {
            $subtotal = 0.0;
            $productMap = [];

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if (! $product->is_active) {
                    throw new \DomainException("Product {$product->name} is not available.");
                }

                if ($product->isManual() && empty($item['payload'])) {
                    throw new \DomainException("Manual product {$product->name} requires payload data.");
                }

                // Pre-flight check for automation products
                if ($product->is_automation) {
                    $requiredParams = is_array($product->params) ? $product->params : [];
                    $suppliedParams = is_array($item['payload'] ?? null) ? $item['payload'] : [];

                    if (count($requiredParams) > 0) {
                        $filled = array_filter($suppliedParams, fn ($v) => is_string($v) && trim($v) !== '');
                        if (count($filled) < count($requiredParams)) {
                            throw new \DomainException(
                                "Product {$product->name} requires: " . implode(', ', $requiredParams)
                            );
                        }
                    }
                }

                $qty = max(1, (int) $item['quantity']);

                $unitPrice = (float) $product->price;
                if ($storeId) {
                    $pivot = DB::table('store_product')
                        ->where('store_id', $storeId)
                        ->where('product_id', $product->id)
                        ->value('custom_price');
                    if ($pivot !== null) {
                        $unitPrice = (float) $pivot;
                    }
                }

                $subtotal += $unitPrice * $qty;
                $productMap[$product->id] = [
                    'product'    => $product,
                    'quantity'   => $qty,
                    'payload'    => $item['payload'] ?? null,
                    'unit_price' => $unitPrice,
                ];
            }

            $fee = 0.0;
            $total = $subtotal + $fee;

            $isManualOrder = collect($productMap)->every(fn ($i) => $i['product']->isManual());

            if (! $isManualOrder && $paymentMethod === 'cash_wallet') {
                $lockedUser = User::lockForUpdate()->findOrFail($user->id);
                if ((float) $lockedUser->balance < $total) {
                    throw new \DomainException('Insufficient balance.');
                }
                $user = $lockedUser;
            }

            $hasAutomation = collect($productMap)->contains(fn ($i) => $i['product']->is_automation);

            $status = $isManualOrder
                ? OrderStatus::Pending
                : ($hasAutomation ? OrderStatus::Processing : OrderStatus::Completed);

            $paymentRef = match ($paymentMethod) {
                'cash_wallet' => 'wallet-'.uniqid(),
                'binance_pay', 'usdt', 'partner_api' => $this->simulatePaymentRef($paymentMethod, $meta, $total),
                default => null,
            };

            if (! $isManualOrder && in_array($paymentMethod, ['binance_pay', 'usdt']) && empty($paymentRef)) {
                $status = OrderStatus::Pending;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => $status,
                'subtotal' => $subtotal,
                'fee' => $fee,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'payment_ref' => $paymentRef,
            ]);

            foreach ($productMap as $productId => $data) {
                /** @var Product $product */
                $product = $data['product'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                    'payload' => $data['payload'],
                ]);

                if (! $product->isManual()) {
                    $product->decrement('stock', $data['quantity']);
                }
            }

            if (! $isManualOrder) {
                if ($paymentMethod === 'cash_wallet') {
                    $user->decrement('balance', $total);
                    Transaction::create([
                        'user_id' => $user->id,
                        'type' => TransactionType::Purchase,
                        'amount' => $total,
                        'fee' => 0,
                        'status' => TransactionStatus::Approved,
                        'method' => 'cash_wallet',
                        'gateway_ref' => $order->payment_ref,
                        'meta' => ['order_id' => $order->id],
                    ]);
                } elseif (in_array($paymentMethod, ['binance_pay', 'usdt', 'partner_api'])) {
                    if ($order->payment_ref) {
                        Transaction::create([
                            'user_id' => $user->id,
                            'type' => TransactionType::Purchase,
                            'amount' => $total,
                            'fee' => 0,
                            'status' => TransactionStatus::Approved,
                            'method' => $paymentMethod,
                            'gateway_ref' => $order->payment_ref,
                            'meta' => [
                                'order_id' => $order->id,
                                'payment_meta' => $meta,
                            ],
                        ]);
                    }
                }

                if ($hasAutomation) {
                    $this->safeBroadcast(new OrderCreated($order));
                } else {
                    $this->safeBroadcast(new OrderCompleted($order));
                }
            } else {
                $this->safeBroadcast(new OrderCreated($order));
            }

            try {
                $user->notify(new OrderStatusChanged($order));
            } catch (\Throwable $e) {
                Log::warning('Notification failed (non-fatal)', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                Mail::to($user->email)->send(new OrderConfirmation($order));
            } catch (\Throwable $e) {
                Log::warning('Order confirmation email failed (non-fatal)', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order->fresh(['items.product']);
        });

        // After commit — attempt auto-fulfillment for automation items via Oranos.
        $anyFailed = false;
        if ($order->items->contains(fn ($i) => $i->product?->is_automation)) {
            $this->fulfillAutomationItems($order);
            $order = $order->fresh(['items.product']);
            $anyFailed = $order->status === OrderStatus::Rejected;
        }

        // Credit the store owner if the sale came from a store and didn't fail.
        if ($storeId && ! $anyFailed) {
            $this->creditStoreOwner($order, $storeId);
            $order = $order->fresh(['items.product']);
        }

        return $order;
    }

    /**
     * Send automation items to Oranos, then check their status.
     * Oranos status values: "accept", "reject", "wait"
     */
    private function fulfillAutomationItems(Order $order): void
    {
        $oranosService = app(OranosMarketService::class);
        $oranosOrderIds = [];
        $hasFailure = false;
        $failureReason = '';

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_automation || empty($product->oranos_product_id)) {
                continue;
            }

            $payload = is_array($item->payload) ? $item->payload : [];
            $playerId = (string) ($payload['id'] ?? $payload['player_id'] ?? $payload['user_id'] ?? (array_is_list($payload) ? ($payload[0] ?? '') : ''));
            if ($playerId === '') {
                $hasFailure = true;
                $failureReason = 'Missing required playerId for product: ' . $product->name;
                Log::warning('Oranos fulfillment skipped: missing playerId', [
                    'local_order_id' => $order->id,
                    'product_id' => $product->id,
                ]);
                continue;
            }

            $extraParams = [];
            if (is_array($product->params) && is_array($payload = $item->payload)) {
                if (array_is_list($payload)) {
                    foreach ($product->params as $i => $param) {
                        $extraParams[$param] = $payload[$i];
                    }
                } else {
                    foreach ($product->params as $param) {
                        $extraParams[$param] = $payload[$param];
                    }
                }
            }

            try {
                $response = $oranosService->createOrder(
                    (int) $product->oranos_product_id,
                    (int) $item->quantity,
                    $playerId,
                    $extraParams
                );

                if (isset($response['data']['order_id']) || isset($response['order_id'])) {
                    $oranosOrderId = $response['data']['order_id'] ?? $response['order_id'];
                    $oranosOrderIds[] = $oranosOrderId;
                    $order->update([
                        'oranos_order_id' => (string) $oranosOrderId,
                        'oranos_status' => $response['data']['status'] ?? 'wait',
                    ]);
                    Log::info('Oranos order created', [
                        'local_order_id' => $order->id,
                        'oranos_order_id' => $oranosOrderId,
                        'product_id' => $product->id,
                    ]);
                } else {
                    $hasFailure = true;
                    $failureReason = 'Oranos returned unexpected response for product: ' . $product->name;
                    Log::warning('Oranos createOrder unexpected response', [
                        'local_order_id' => $order->id,
                        'response' => $response,
                    ]);
                }
            } catch (\Throwable $e) {
                $hasFailure = true;
                $failureReason = 'Oranos API error: ' . $e->getMessage();
                Log::error('Oranos createOrder failed', [
                    'local_order_id' => $order->id,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($hasFailure) {
            $this->refundFailedOrder($order, $failureReason);
            return;
        }

        if (! empty($oranosOrderIds)) {
            // Check order statuses from Oranos — valid values: accept / reject / wait
            try {
                $checkResponse = $oranosService->checkOrders($oranosOrderIds);
                $allCompleted = true;
                $anyRejected = false;

                if (isset($checkResponse['data']) && is_array($checkResponse['data'])) {
                    foreach ($checkResponse['data'] as $oranosOrder) {
                        $status = strtolower((string) ($oranosOrder['status'] ?? ''));
                        if ($status === 'reject') {
                            $anyRejected = true;
                            $allCompleted = false;
                            break;
                        }
                        if ($status !== 'accept') {
                            $allCompleted = false;
                        }
                    }
                } else {
                    $allCompleted = false;
                }

                if ($anyRejected) {
                    $this->refundFailedOrder($order, 'Oranos rejected the order');
                    return;
                }

                if ($allCompleted) {
                    $order->update(['status' => OrderStatus::Completed, 'oranos_status' => 'accept']);
                    $this->safeBroadcast(new OrderCompleted($order));
                    $order->user->notify(new OrderStatusChanged($order));
                    return;
                }

                // Still "wait" — keep as Processing (admin/cron will follow up)
                $order->update(['oranos_status' => 'wait']);
            } catch (\Throwable $e) {
                Log::warning('Oranos checkOrders failed', [
                    'local_order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($order->status === OrderStatus::Completed) {
            $order->update(['status' => OrderStatus::Processing]);
        }
    }

    private function refundFailedOrder(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->update([
                'status' => OrderStatus::Rejected,
                'failure_reason' => $reason,
            ]);

            $user = $order->user;
            $user->increment('balance', (float) $order->total);

            Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::Refund,
                'amount' => $order->total,
                'fee' => 0,
                'status' => TransactionStatus::Approved,
                'method' => $order->payment_method,
                'gateway_ref' => 'refund-' . $order->payment_ref,
                'meta' => [
                    'order_id' => $order->id,
                    'original_payment_ref' => $order->payment_ref,
                    'refund_reason' => $reason,
                ],
            ]);

            $this->safeBroadcast(new OrderCompleted($order));
            $user->notify(new OrderStatusChanged($order));
        });
    }

    private function creditStoreOwner(Order $order, int $storeId): void
    {
        $store = Store::with('user')->find($storeId);
        if (! $store || ! $store->user) {
            return;
        }

        $profit = 0.0;
        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }
            $profit += ((float) $item->unit_price - (float) $product->price) * (int) $item->quantity;
        }

        if ($profit <= 0) {
            return;
        }

        DB::transaction(function () use ($store, $order, $profit) {
            $store->user->increment('balance', $profit);

            Transaction::create([
                'user_id' => $store->user->id,
                'type'    => TransactionType::StoreEarning,
                'amount'  => $profit,
                'fee'     => 0,
                'status'  => TransactionStatus::Approved,
                'method'  => 'store_sale',
                'meta'    => [
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                ],
            ]);
        });
    }

    public function markCompleted(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Completed]);
            $this->safeBroadcast(new OrderCompleted($order));
            $order->user->notify(new OrderStatusChanged($order));
        });
    }

    private function safeBroadcast(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed (non-fatal)', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function simulatePaymentRef(string $paymentMethod, array $meta, float $total): ?string
    {
        if ($paymentMethod === 'partner_api') {
            return 'partner_api_'.uniqid();
        }

        $gateway = app(PaymentGatewayManager::class)->driver($paymentMethod);

        if (! $gateway->isDemoMode()) {
            return null;
        }

        $result = $gateway->simulatePayment([...$meta, 'amount' => $total]);

        if (! ($result['success'] ?? false)) {
            Log::warning('Payment simulation returned failure', [
                'method' => $paymentMethod,
                'result' => $result,
            ]);

            return $paymentMethod.'_failed_'.uniqid();
        }

        return $result['transaction_id'] ?? $paymentMethod.'_'.uniqid();
    }

    public function markRejected(Order $order, ?string $reason = null): void
    {
        if ($order->status === OrderStatus::Rejected) {
            return;
        }
        if ($order->payment_method === 'cash_wallet') {
            $this->refundFailedOrder($order, $reason ?? 'Rejected by admin');
            return;
        }
        DB::transaction(function () use ($order, $reason) {
            $order->update([
                'status' => OrderStatus::Rejected,
                'notes' => $reason ? "Rejected: {$reason}" : $order->notes,
            ]);
            $this->safeBroadcast(new OrderCompleted($order));
            $order->user->notify(new OrderStatusChanged($order));
        });
    }

    public function markProcessing(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Processing]);
            $this->safeBroadcast(new OrderCompleted($order));
            $order->user->notify(new OrderStatusChanged($order));
        });
    }
}