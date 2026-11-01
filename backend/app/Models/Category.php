<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'name_ar',
        'slug',
        'type',
        'description',
        'description_ar',
        'icon',
        'image_base64',
        'image_url',
        'admin_image_url',
        'oranos_image_url',
        'sort_order',
        'form_schema',
        'oranos_category_id',
        'is_visible',
    ];

    protected $casts = [
        'type' => CategoryType::class,
        'sort_order' => 'integer',
        'form_schema' => 'array',
        'oranos_category_id' => 'integer',
        'is_visible' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
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

    /** IDs reachable through an entirely visible category ancestry. */
    public static function storefrontVisibleIds(): array
    {
        $rows = static::query()->get(['id', 'parent_id', 'is_visible']);
        $byParent = $rows->groupBy(fn ($row) => $row->parent_id === null ? 0 : (int) $row->parent_id);
        $visible = [];
        $queue = [0];
        $visited = [0 => true];

        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($byParent->get($parentId, collect()) as $category) {
                $id = (int) $category->id;
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                if ($category->is_visible === false) {
                    continue;
                }
                $visible[] = $id;
                $queue[] = $id;
            }
        }

        return $visible;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function manualOrderFields(): HasMany
    {
        return $this->hasMany(ManualOrderField::class);
    }

    public function isManual(): bool
    {
        return $this->type === CategoryType::Manual;
    }

    public function getSubtreeIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getSubtreeIds());
        }

        return $ids;
    }
}
