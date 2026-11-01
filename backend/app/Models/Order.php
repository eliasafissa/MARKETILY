<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'status',
        'subtotal',
        'fee',
        'total',
        'payment_method',
        'payment_ref',
        'idempotency_key',
        'notes',
        'oranos_order_id',
        'oranos_status',
        'commission',
        'failure_reason',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'decimal:24',
        'fee' => 'decimal:24',
        'total' => 'decimal:24',
        'commission' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isManual(): bool
    {
        return $this->items->every(fn(OrderItem $item) => $item->product->isManual());
    }

    public function markCompleted(): void
    {
        $this->update(['status' => OrderStatus::Completed]);
    }

    public function markRejected(): void
    {
        $this->update(['status' => OrderStatus::Rejected]);
    }
}
