<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncOranosCategories extends Command
{
    protected $signature = 'oranos:sync-categories';

    protected $description = 'Sync categories from Oranos Market API';

    public function handle(OranosMarketService $service): int
    {
        try {
            $categories = $service->getCategories();
        } catch (\Throwable $e) {
            Log::error('Failed to fetch Oranos categories', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch categories: '.$e->getMessage());

            return 1;
        }

        $synced = 0;
        $failed = 0;
        $categoriesUpdated = 0;

        foreach ($categories as $categoryData) {
            try {
                $oranosId = $categoryData['id'] ?? null;
                $name = $categoryData['name'] ?? null;
                $parentId = $categoryData['parent_id'] ?? null;
                $categoryImg = $categoryData['image_url'] ?? $categoryData['image'] ?? null;
                $categoryImgBase64 = $categoryData['image_base64'] ?? null;

                if (! $name) {
                    continue;
                }

                $category = $this->findOrCreateCategory($name, $oranosId);

                // Handle parent category
                if ($parentId) {
                    $parent = Category::where('oranos_category_id', $parentId)->first();
                    if ($parent && $category->parent_id !== $parent->id) {
                        $category->update(['parent_id' => $parent->id]);
                    }
                }

                // Update category image if changed
                $updateData = [];
                if ($category->image_url !== $categoryImg) {
                    $updateData['image_url'] = $categoryImg;
                }
                if ($categoryImgBase64 && $category->image_base64 !== $categoryImgBase64) {
                    $updateData['image_base64'] = $categoryImgBase64;
                }
                if (! empty($updateData)) {
                    $category->update($updateData);
                    $categoriesUpdated++;
                }

                // Store Oranos category ID for parent linking
                if ($oranosId && ! $category->oranos_category_id) {
                    $category->update(['oranos_category_id' => $oranosId]);
                }

                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Failed to sync Oranos category', ['oranos_id' => $categoryData['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Synced {$synced} categories. Failed: {$failed}. Categories updated: {$categoriesUpdated}.");

        return 0;
    }

    /**
     * Find or create a category by name, with fallback to oranos_category_id.
     * Avoids creating duplicate categories by matching on name_ar/name first.
     * Guarantees unique slug even under concurrent inserts.
     */
    private function findOrCreateCategory(string $name, ?int $oranosId = null): Category
    {
        $name = trim($name);

        // 1. Exact match by name_ar or name (handles Arabic correctly)
        $category = Category::where('name_ar', $name)
            ->orWhere('name', $name)
            ->first();
        if ($category) {
            return $category;
        }

        // 1b. Fuzzy fallback: strip RTL/invisible marks before comparing
        $clean = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $name);
        if ($clean !== $name) {
            $category = Category::whereRaw(
                "REPLACE(REPLACE(REPLACE(name_ar, CHAR(0x200F USING utf8mb4), ''), ' ', ''), ' ', '') = ?",
                [str_replace(' ', '', $clean)]
            )->first();
            if ($category) {
                return $category;
            }
        }

        // 2. Match by oranos_category_id
        if ($oranosId) {
            $category = Category::where('oranos_category_id', $oranosId)->first();
            if ($category) {
                return $category;
            }
        }

        // 3. Build a GUARANTEED-unique slug
        $base = Str::slug($name);
        if (empty($base)) {
            $base = 'cat-' . substr(md5($name), 0, 12);
        }
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        // 4. Create with race-condition fallback
        try {
            return Category::create([
                'slug' => $slug,
                'name' => $name,
                'name_ar' => $name,
                'type' => 'auto',
                'icon' => 'package',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Race: someone else created it between our check and insert
            if (str_contains($e->getMessage(), 'categories_slug_unique')
                || str_contains($e->getMessage(), 'Duplicate entry')) {
                // Try to find it by name or slug one more time
                $category = Category::where('name_ar', $name)
                    ->orWhere('name', $name)
                    ->orWhere('slug', $base)
                    ->first();
                if ($category) {
                    return $category;
                }
            }
            throw $e;
        }
    }
}
