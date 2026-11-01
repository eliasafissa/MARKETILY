<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\ProcessOranosPurchase;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Services\VipService;
use App\Services\CatalogPricing;
use App\Notifications\OrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(User $user, array $items, string $paymentMethod = 'cash_wallet', array $meta = [], ?int $storeId = null, string|float|null $expectedTotal = null, ?string $idempotencyKey = null): Order
    {
        if (! in_array($paymentMethod, ['cash_wallet', 'partner_api'], true)) {
            throw new \DomainException('Fund your site wallet before checkout.');
        }
        if (! count($items) || count($items) > 20) {
            throw new \DomainException('Order must have 1 to 20 items.');
        }
        if (count(array_unique(array_column($items, 'product_id'))) !== count($items)) {
            throw new \DomainException('Duplicate product rows are not allowed.');
        }

        // User wallet and products are locked while the order and purchase ledger are created.
        $alreadyProcessed = false;
        $order = DB::transaction(function () use ($user, $items, $paymentMethod, $meta, $storeId, $expectedTotal, $idempotencyKey, &$alreadyProcessed) {
            $lockedUser = User::lockForUpdate()->findOrFail($user->id);
            if ($idempotencyKey !== null) {
                $existing = Order::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    $alreadyProcessed = true;
                    return $existing->load('items.product');
                }
            }
            $vipLevel = app(VipService::class)->syncLevel($lockedUser);
            $lines = [];
            $subtotal = PreciseMoney::decimal('0');
            $needsManual = false;
            $hasOranos = false;
            $store = $storeId ? Store::find($storeId) : null;
            if ($storeId && ! $store) {
                throw new \DomainException('Store not found.');
            }

            foreach ($items as $input) {
                $product = Product::lockForUpdate()->findOrFail((int) $input['product_id']);
                $qty = (int) ($input['quantity'] ?? 0);
                $payload = $input['payload'] ?? [];
                if (! $product->is_active || ($product->oranos_product_id && ! $product->oranos_available)) {
                    throw new \DomainException("Product {$product->name} unavailable.");
                }
                if ($qty < 1 || $qty > 1000000) {
                    throw new \DomainException('Invalid product quantity.');
                }
                $this->validateQuantity($product, $qty);
                $this->validatePayload($product, $payload);

                $isProvider = $product->is_automation && (bool) $product->oranos_product_id;
                $hasOranos = $hasOranos || $isProvider;
                $needsManual = $needsManual || ! $isProvider;
                if (! $isProvider && (int) $product->stock < $qty) {
                    throw new \DomainException("Insufficient stock for {$product->name}.");
                }

                $storePrice = null;
                if ($store) {
                    $storePrice = DB::table('store_product')
                        ->where('store_id', $store->id)->where('product_id', $product->id)->value('custom_price');
                    if ($storePrice === null || PreciseMoney::compare((string) $storePrice, (string) $product->price) < 0) {
                        throw new \DomainException('Invalid store price.');
                    }
                }
                $unitPrice = CatalogPricing::exactForProduct($product, $vipLevel, $storePrice === null ? null : (string) $storePrice);
                $subtotal = PreciseMoney::add($subtotal, PreciseMoney::multiplyByQuantity($unitPrice, $qty));
                $lines[] = compact('product', 'qty', 'payload', 'unitPrice', 'isProvider');
            }


            if (PreciseMoney::compare($subtotal, '0') <= 0) {
                throw new \DomainException('Invalid order total.');
            }
            if ($expectedTotal !== null && PreciseMoney::compare((string) $expectedTotal, $subtotal) !== 0) {
                throw new \DomainException('Prices changed. Refresh the cart and review the new total.');
            }
            $walletFunded = in_array($paymentMethod, ['cash_wallet', 'partner_api'], true);
            if ($walletFunded && PreciseMoney::compare((string) $lockedUser->balance, $subtotal) < 0) {
                throw new \DomainException('Insufficient balance.');
            }
            // Non-wallet payment fields are user-reported, NOT proof of payment.
            $status = (! $walletFunded || $needsManual) ? OrderStatus::Pending : OrderStatus::Processing;
            $order = Order::create([
                'user_id' => $lockedUser->id,
                'idempotency_key' => $idempotencyKey,
                'store_id' => $storeId,
                'status' => $status,
                'subtotal' => $subtotal,
                'fee' => 0,
                'total' => $subtotal,
                'payment_method' => $paymentMethod,
                'payment_ref' => $walletFunded ? 'wallet-' . Str::uuid() : null,
                'notes' => $walletFunded ? null : 'Pending independent payment verification: ' . substr(json_encode(array_intersect_key($meta, array_flip(['binance_id', 'tx_hash', 'network'])), JSON_UNESCAPED_UNICODE), 0, 1500),
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['unitPrice'],
                    'payload' => $line['payload'],
                    'oranos_order_uuid' => ($line['isProvider'] && ! $needsManual && $walletFunded) ? (string) Str::uuid() : null,
                ]);
                if (! $line['isProvider']) {
                    $line['product']->decrement('stock', $line['qty']);
                }
            }
            if ($walletFunded) {
                $lockedUser->decrement('balance', $subtotal);
                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'type' => TransactionType::Purchase,
                    'amount' => $subtotal,
                    'fee' => 0,
                    'status' => TransactionStatus::Approved,
                    'method' => $paymentMethod,
                    'gateway_ref' => $order->payment_ref,
                    'meta' => ['order_id' => $order->id],
                ]);
            }
            return $order;
        });

        if ($alreadyProcessed) {
            return $order->fresh(['items.product']);
        }
        $this->broadcastSafely(new OrderCreated($order));
        $this->notifySafely($order);
        // Only paid wallet orders with provider-only items may be sent immediately.
        if ($order->status === OrderStatus::Processing && $order->items()->whereNotNull('oranos_order_uuid')->exists()) {
            if (config('services.oranos.async_orders', false)) {
                // Requires a dedicated queue worker: php artisan queue:work --queue=oranos,default --tries=1.
                ProcessOranosPurchase::dispatch($order->id)->onQueue('oranos');
            } else {
                $this->fulfillAutomationItems($order);
            }
        }
        return $order->fresh(['items.product']);
    }

    /** Read-only cart quote; checkout always recalculates under row locks. */
    public function quoteOrder(User $user, array $items, ?int $storeId = null): array
    {
        if (! count($items) || count($items) > 20 ||
            count(array_unique(array_column($items, 'product_id'))) !== count($items)) {
            throw new \DomainException('Invalid order items.');
        }
        $level = app(VipService::class)->effectiveLevel($user);
        $store = $storeId ? Store::find($storeId) : null;
        if ($storeId && ! $store) throw new \DomainException('Store not found.');
        $total = PreciseMoney::decimal('0');
        $lines = [];
        foreach ($items as $input) {
            $product = Product::findOrFail((int) ($input['product_id'] ?? 0));
            $qty = (int) ($input['quantity'] ?? 0);
            if (! $product->is_active || ($product->oranos_product_id && ! $product->oranos_available) || $qty < 1 || $qty > 1000000) {
                throw new \DomainException('Product unavailable or invalid quantity.');
            }
            $this->validateQuantity($product, $qty);
            $this->validatePayload($product, $input['payload'] ?? []);
            if (! ($product->is_automation && $product->oranos_product_id) && $product->stock < $qty) {
                throw new \DomainException('Insufficient stock.');
            }
            $storePrice = null;
            if ($store) {
                $storePrice = DB::table('store_product')->where('store_id', $store->id)->where('product_id', $product->id)->value('custom_price');
                if ($storePrice === null || PreciseMoney::compare((string) $storePrice, (string) $product->price) < 0) {
                    throw new \DomainException('Invalid store price.');
                }
            }
            $effective = CatalogPricing::exactForProduct($product, $level, $storePrice === null ? null : (string) $storePrice);
            $lineTotal = PreciseMoney::multiplyByQuantity($effective, $qty);
            $total = PreciseMoney::add($total, $lineTotal);
            $lines[] = ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $effective, 'line_total' => $lineTotal];
        }
        return [
            'vip_level' => $level->value,
            'vip_discount_percent' => CatalogPricing::percentageFor($level),
            'total' => $total,
            'items' => $lines,
        ];
    }

    private function validateQuantity(Product $product, int $qty): void
    {
        if (! $product->oranos_product_id) {
            return;
        }
        $allowed = $product->qty_values;
        if ($allowed === null) {
            if ($qty !== 1) throw new \DomainException('Quantity for this Oranos package must be 1.');
            return;
        }
        if (is_array($allowed) && array_is_list($allowed)) {
            if (! in_array($qty, array_map('intval', $allowed), true)) {
                throw new \DomainException('Quantity not among the allowed tiers.');
            }
            return;
        }
        if (is_array($allowed) && isset($allowed['min'], $allowed['max'])) {
            if ($qty < (int) $allowed['min'] || $qty > (int) $allowed['max']) {
                throw new \DomainException('Quantity is outside provider bounds.');
            }
            return;
        }
        throw new \DomainException('Unknown provider quantity format; manual review required.');
    }

    private function validatePayload(Product $product, mixed $payload): void
    {
        if (! is_array($payload)) {
            throw new \DomainException('Invalid order fields.');
        }
        $fields = is_array($product->params) ? $product->params : [];
        if (! $product->oranos_product_id && count($payload) === 0) {
            throw new \DomainException('Manual product requires order details.');
        }
        if (count($payload) > 30 || count($fields) > 20) {
            throw new \DomainException('Too many order fields.');
        }
        foreach ($fields as $i => $label) {
            $value = array_is_list($payload) ? ($payload[$i] ?? null) : ($payload[$label] ?? null);
            if (! is_scalar($value) || trim((string) $value) === '' || mb_strlen((string) $value) > 500) {
                throw new \DomainException('Required order field missing or invalid: ' . (string) $label);
            }
        }
        foreach ($payload as $value) {
            if (! is_scalar($value) || mb_strlen((string) $value) > 500) {
                throw new \DomainException('Invalid order field value.');
            }
        }
    }

    // Queue worker entry point (idempotent against already stored provider-order IDs).
    public function processAutomationOrder(Order $order): void
    {
        if ($order->status !== OrderStatus::Processing) return;
        $this->fulfillAutomationItems($order);
    }

    // Never retry a submission with a fresh UUID. If the connection fails after an upstream
    // charge, leave it for UUID-based reconciliation to avoid duplicate charges.
    private function fulfillAutomationItems(Order $order): void
    {
        $service = app(OranosMarketService::class);
        foreach ($order->items()->with('product')->get() as $item) {
            if (! $item->oranos_order_uuid || $item->oranos_order_id) continue;
            $product = $item->product;
            $payload = $item->payload ?? [];
            $params = is_array($product?->params) ? $product->params : [];
            $values = array_is_list($payload) ? $payload : array_map(fn ($p) => $payload[$p] ?? '', $params);
            $playerId = (string) ($values[0] ?? $payload['playerId'] ?? '');
            $extra = [];
            foreach ($params as $i => $label) {
                if (isset($values[$i])) $extra[(string) $label] = (string) $values[$i];
            }
            try {
                $response = $service->createOrder(
                    (int) $product->oranos_product_id, $item->quantity, $playerId, $extra,
                    $item->oranos_order_uuid
                );
                $row = is_array($response['data'] ?? null) ? $response['data'] : $response;
                if (array_is_list($row) && count($row) === 1 && is_array($row[0])) $row = $row[0];
                $rawId = is_array($row) ? ($row['order_id'] ?? $row['id'] ?? null) : null;
                $id = is_string($rawId) || is_int($rawId) ? (string) $rawId : '';
                $status = strtolower((string) (is_array($row) ? ($row['status'] ?? $row['state'] ?? '') : ''));
                if (! preg_match('/^[A-Za-z0-9_-]{1,190}$/', $id) || ! in_array($status, ['accept', 'reject', 'wait'], true)) {
                    throw new \RuntimeException('Oranos response missing a trustworthy order ID/status; reconcile by original order_uuid before retry.');
                }
                $item->update(['oranos_order_id' => $id, 'oranos_status' => $status]);
                if ($order->items()->whereNotNull('oranos_order_id')->count() === 1) {
                    $order->update(['oranos_order_id' => $id, 'oranos_status' => $status]);
                }
            } catch (\Throwable $e) {
                Log::warning('Oranos needs manual reconciliation', [
                    'order_id' => $order->id, 'item_id' => $item->id, 'reason' => $e->getMessage(),
                ]);
                $item->update(['oranos_status' => 'review']);
                $order->update(['failure_reason' => 'Oranos response uncertain; inspect order UUID before resubmitting.']);
            }
        }
        $this->reconcileOrder($order);
    }

    public function reconcileOrder(Order $order): void
    {
        $order->refresh()->load('items.product');
        if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Rejected], true)) return;
        if ($order->items->contains(fn ($i) => ! $i->oranos_order_uuid)) return; // manual item(s)
        $statuses = $order->items->pluck('oranos_status')->all();
        if (in_array('review', $statuses, true) || in_array(null, $statuses, true)) return;
        if (count(array_filter($statuses, fn ($s) => $s === 'accept')) === count($statuses)) {
            $this->markCompleted($order);
            return;
        }
        if (count(array_filter($statuses, fn ($s) => $s === 'reject')) === count($statuses)) {
            $this->refundFailedOrder($order, 'All Oranos items rejected');
            return;
        }
        if (in_array('reject', $statuses, true)) {
            $order->update(['failure_reason' => 'Mixed Oranos statuses require manual reconciliation']);
        }
    }

    public function refundFailedOrder(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($locked->status === OrderStatus::Rejected) return;
            $locked->update(['status' => OrderStatus::Rejected, 'failure_reason' => $reason]);
            foreach ($locked->items()->with('product')->get() as $item) {
                if ($item->product && ! ($item->product->is_automation && $item->product->oranos_product_id)) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
            if (! in_array($locked->payment_method, ['cash_wallet', 'partner_api'], true)) return;
            $ref = 'refund-order-' . $locked->id;
            if (Transaction::where('gateway_ref', $ref)->exists()) return;
            $lockedUser = User::lockForUpdate()->findOrFail($locked->user_id);
            $lockedUser->increment('balance', $locked->total);
            Transaction::create([
                'user_id' => $lockedUser->id,
                'type' => TransactionType::Refund,
                'amount' => $locked->total,
                'fee' => 0,
                'status' => TransactionStatus::Approved,
                'method' => $locked->payment_method,
                'gateway_ref' => $ref,
                'meta' => ['order_id' => $locked->id, 'refund_reason' => $reason],
            ]);
        });
        $this->notifySafely($order->fresh());
    }

    public function markCompleted(Order $order): void
    {
        if ($order->status === OrderStatus::Completed || $order->status === OrderStatus::Rejected) return;
        if (! in_array($order->payment_method, ['cash_wallet', 'partner_api'], true)) {
            throw new \DomainException('Unverified external payment; cannot complete this order.');
        }
        if ($order->items()->whereNotNull('oranos_order_uuid')->where('oranos_status', '!=', 'accept')->exists() ||
            $order->items()->whereNotNull('oranos_order_uuid')->whereNull('oranos_status')->exists()) {
            throw new \DomainException('Oranos has not confirmed every provider item.');
        }
        DB::transaction(function () use ($order) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== OrderStatus::Completed && $locked->status !== OrderStatus::Rejected) {
                $locked->update(['status' => OrderStatus::Completed, 'failure_reason' => null]);
                $this->creditStoreOwner($locked);
            }
        });
        app(VipService::class)->syncLevel($order->user()->firstOrFail());
        $this->broadcastSafely(new OrderCompleted($order));
        $this->notifySafely($order->fresh());
    }

    private function creditStoreOwner(Order $order): void
    {
        if (! $order->store_id) return;
        $ref = 'store-earning-' . $order->id;
        if (Transaction::where('gateway_ref', $ref)->exists()) return;
        $store = Store::with('user')->find($order->store_id);
        if (! $store?->user) return;
        $order->loadMissing('items.product');
        $profit = PreciseMoney::decimal('0');
        foreach ($order->items as $item) {
            $difference = PreciseMoney::sub(
                (string) $item->unit_price,
                (string) $item->product->price
            );
            if (PreciseMoney::compare($difference, '0') > 0) {
                $profit = PreciseMoney::add(
                    $profit,
                    PreciseMoney::multiplyByQuantity($difference, (int) $item->quantity)
                );
            }
        }
        if (PreciseMoney::compare($profit, '0') <= 0) return;
        $owner = User::lockForUpdate()->findOrFail($store->user_id);
        $owner->increment('balance', $profit);
        Transaction::create([
            'user_id' => $owner->id,
            'type' => TransactionType::StoreEarning,
            'amount' => $profit,
            'fee' => 0,
            'status' => TransactionStatus::Approved,
            'method' => 'store_sale',
            'gateway_ref' => $ref,
            'meta' => ['order_id' => $order->id, 'store_id' => $store->id],
        ]);
    }

    public function markRejected(Order $order, ?string $reason = null): void
    {
        if ($order->status === OrderStatus::Completed) {
            throw new \DomainException('Completed order cannot be automatically refunded; investigate manually.');
        }
        if ($order->items()->whereNotNull('oranos_order_id')->whereNotIn('oranos_status', ['reject'])->exists()) {
            throw new \DomainException('Provider accepted/pending order; reconcile with Oranos before refund.');
        }
        $this->refundFailedOrder($order, $reason ?? 'Rejected by admin');
    }

    public function markProcessing(Order $order): void
    {
        if ($order->status === OrderStatus::Rejected || $order->status === OrderStatus::Completed) {
            throw new \DomainException('Cannot reopen settled order.');
        }
        $order->update(['status' => OrderStatus::Processing]);
        $this->notifySafely($order);
    }

    private function broadcastSafely(object $event): void
    {
        try { event($event); } catch (\Throwable $e) { Log::warning('Broadcast unavailable', ['event' => $event::class]); }
    }

    private function notifySafely(Order $order): void
    {
        try { $order->user?->notify(new OrderStatusChanged($order)); }
        catch (\Throwable $e) { Log::warning('Order notification unavailable', ['order_id' => $order->id]); }
    }
}
