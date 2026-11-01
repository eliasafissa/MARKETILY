<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'fee',
        'status',
        'method',
        'gateway_ref',
        'payment_reference',
        'rejection_reason',
        'meta',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'amount' => 'decimal:24',
        'fee' => 'decimal:24',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === TransactionStatus::Approved;
    }

    public function getNetAttribute(): float
    {
        return (float) $this->amount - (float) $this->fee;
    }
}
