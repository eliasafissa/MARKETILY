<?php

namespace App\Models;

use App\Enums\VipLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'vip_level',
        'balance',
        'banned_at',
        'api_key',
        'api_key_generated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'vip_level' => VipLevel::class,
        'balance' => 'decimal:2',
        'banned_at' => 'datetime',
        'api_key_generated_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function deposits()
    {
        return $this->hasMany(Transaction::class)->where('type', 'deposit');
    }

    public function withdrawals()
    {
        return $this->hasMany(Transaction::class)->where('type', 'withdrawal');
    }

        public function stores()
    {
        return $this->hasMany(\App\Models\Store::class);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function hasVipLevel(VipLevel $level): bool
    {
        return $this->vip_level === $level;
    }

    public function isVip(): bool
    {
        return $this->vip_level !== VipLevel::None;
    }

    public function partnerApiRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PartnerApiRequest::class);
    }
}
