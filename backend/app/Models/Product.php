<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'external_store_id',
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
        'image_base64',
        'image_url',
        'admin_image_url',
        'oranos_image_url',
        'icon',
        'price',
        'stock',
        'type',
        'is_active',
        'metadata',
        'oranos_product_id',
        'base_price',
        'pricing_mode',
        'is_automation',
        'qty_values',
        'params',
        'oranos_available',
    ];

    protected $casts = [
        'price' => 'decimal:24',
        'stock' => 'integer',
        'type' => CategoryType::class,
        'is_active' => 'boolean',
        'metadata' => 'array',
        'params' => 'array',
        'qty_values' => 'array',
        'base_price' => 'decimal:24',
        'is_automation' => 'boolean',
        'oranos_product_id' => 'integer',
        'oranos_available' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /** Admin upload overrides the provider fallback without overwriting either source. */
    public function getImageUrlAttribute(?string $legacy): ?string
    {
        return $this->absolutePublicImage(
            ($this->attributes['admin_image_url'] ?? null)
            ?: (($this->attributes['oranos_image_url'] ?? null) ?: $legacy)
        );
    }

    public function getAdminImageUrlAttribute(?string $value): ?string
    {
        return $this->absolutePublicImage($value);
    }

    private function absolutePublicImage(?string $image): ?string
    {
        if ($image !== null && str_starts_with($image, '/storage/')) {
            return request()->getSchemeAndHttpHost() . $image;
        }
        return $image;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function externalStore(): BelongsTo
    {
        return $this->belongsTo(ExternalStore::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

        public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_product')
            ->withPivot('custom_price')
            ->withTimestamps();
    }

    public function isManual(): bool
    {
        return $this->type === CategoryType::Manual;
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function hasExternalStore(): bool
    {
        return $this->external_store_id !== null;
    }
}
