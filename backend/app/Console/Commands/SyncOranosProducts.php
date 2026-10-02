<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncOranosProducts extends Command
{
    protected $signature = 'oranos:sync-products';

    protected $description = 'Sync products from Oranos Market API';

    public function handle(OranosMarketService $service): int
    {
        // First, try to fetch categories from Oranos API (if endpoint exists)
        $this->info('Fetching categories from Oranos...');
        $this->syncCategories($service);

        try {
            $products = $service->getProducts();
        } catch (\Throwable $e) {
            Log::error('Oranos getProducts failed', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch products: '.$e->getMessage());

            return 1;
        }

        $markupPercent = (float) Setting::get('oranos_markup_percent', 20);
        $markup = 1 + ($markupPercent / 100);

        $defaultCategory = Category::firstOrCreate(
            ['slug' => 'oranos-other'],
            ['name' => 'Other', 'name_ar' => 'أخرى', 'type' => 'auto', 'icon' => 'package']
        );

        $synced = 0;
        $failed = 0;
        $sellable = 0;
        $unsellable = 0;
        $categoriesUpdated = 0;

        foreach ($products as $product) {
            try {
                $oranosId = $product['id'];
                $name = $product['name'];
                $oranosPrice = (float) ($product['price'] ?? 0);
$hasQtyValues = ! empty($product['qty_values']);

                $ourCost = $oranosPrice;
                $ourRetail = round($ourCost * $markup, 2);

                $oranosAvailable = $product['available'] ?? $product['is_available'] ?? $product['status'] ?? true;
                if (is_string($oranosAvailable)) {
                    $oranosAvailable = in_array(strtolower($oranosAvailable), ['active', 'available', 'in_stock', 'true', '1']);
                }

                $isActive = $oranosAvailable
                    && $ourCost > 0
                    && $ourRetail > $ourCost;

                if ($isActive) {
                    $sellable++;
                } else {
                    $unsellable++;
                }

                $categoryName = $product['category_name'] ?? null;
                $params = $product['params'] ?? null;
                $qtyValues = $product['qty_values'] ?? null;

                // Extract Oranos category image, skip the placeholder
                $rawImg = $product['category_img'] ?? $product['category_image'] ?? $product['category_image_url'] ?? $product['category_image_base64'] ?? null;
                $categoryImg = null;
                $categoryImgBase64 = null;
                if (is_string($rawImg) && $rawImg !== '' && ! str_contains($rawImg, 'empty.png') && str_contains($rawImg, '/images/')) {
                    if (str_starts_with($rawImg, 'data:image/') || str_starts_with($rawImg, 'data:application/')) {
                        $categoryImgBase64 = $rawImg;
                    } else {
                        $categoryImg = $rawImg;
                    }
                }

                // Extract Oranos product image
                $productImg = null;
                $productImgBase64 = null;
                foreach (['image', 'image_url', 'img', 'picture', 'thumbnail', 'photo', 'image_base64'] as $imgField) {
                    $rawProductImg = $product[$imgField] ?? null;
                    if (is_string($rawProductImg) && $rawProductImg !== '' && ! str_contains($rawProductImg, 'empty.png') && str_contains($rawProductImg, '/images/')) {
                        if (str_starts_with($rawProductImg, 'data:image/') || str_starts_with($rawProductImg, 'data:application/')) {
                            $productImgBase64 = $rawProductImg;
                        } else {
                            $productImg = $rawProductImg;
                        }
                        break;
                    }
                }

                if ($categoryName) {
                    $category = $this->findOrCreateCategory($categoryName, null);

                    // Only update category images if new value is valid OR category has no image yet
                    $updateData = [];
                    if ($categoryImg !== null || ! $category->image_url) {
                        $updateData['image_url'] = $categoryImg;
                    }
                    if ($categoryImgBase64 !== null || ! $category->image_base64) {
                        $updateData['image_base64'] = $categoryImgBase64;
                    }
                    if (! empty($updateData)) {
                        $category->update($updateData);
                        $categoriesUpdated++;
                    }
                } else {
                    $category = $defaultCategory;
                }

                $slug = Str::slug($name).'-'.$oranosId;
                if (empty($slug)) {
                    $slug = 'product-'.$oranosId;
                }

                $oranosAvailable = $product['available'] ?? $product['is_available'] ?? $product['status'] ?? true;
                    if (is_string($oranosAvailable)) {
                        $oranosAvailable = in_array(strtolower($oranosAvailable), ['active', 'available', 'in_stock', 'true', '1']);
                    }

                    $existing = Product::where('oranos_product_id', $oranosId)->first();

                    $payload = [
                        'category_id' => $category->id,
                        'name' => $name,
                        'name_ar' => $name,
                        'description' => $name,
                        'description_ar' => $name,
                        'base_price' => $ourCost,
                        'price' => $ourRetail,
                        'is_automation' => true,
                        'qty_values' => $qtyValues,
                        'params' => $params,
                        'is_active' => $isActive,
                        'oranos_available' => $oranosAvailable,
                        'stock' => $oranosAvailable ? 999 : 0,
                        'slug' => $slug,
                    ];

                    // Only update image_url if new value is valid OR product has no image yet
                    if ($productImg !== null || ! $existing?->image_url) {
                        $payload['image_url'] = $productImg;
                    }
                    if ($productImgBase64 !== null || ! $existing?->image_base64) {
                        $payload['image_base64'] = $productImgBase64;
                    }

                    Product::updateOrCreate(
                        ['oranos_product_id' => $oranosId],
                        $payload
                    );

                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Failed to sync Oranos product', ['oranos_id' => $product['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }

        $linked = 0;
        $topCategories = Category::whereNull('parent_id')->get();
        foreach ($topCategories as $category) {
            if (preg_match('/^(.+?)\s*\(/', $category->name, $matches)) {
                $parentName = trim($matches[1]);
                $parent = $this->findOrCreateCategory($parentName, null);
                if ($category->parent_id !== $parent->id) {
                    $category->update(['parent_id' => $parent->id]);
                }
                $linked++;
            }
        }

        // Refresh store prices to match current product prices
        $updatedStores = 0;
        $storeMarkupPercent = (float) Setting::get('store_markup_percent', 10);
        $storeMarkup = 1 + ($storeMarkupPercent / 100);

        DB::table('stores')->orderBy('id')->chunk(50, function ($stores) use (&$updatedStores, $storeMarkup) {
            foreach ($stores as $store) {
                $rows = DB::table('store_product')
                    ->join('products', 'store_product.product_id', '=', 'products.id')
                    ->where('store_product.store_id', $store->id)
                    ->where('products.is_automation', true)
                    ->select('store_product.id as pivot_id', 'products.price as product_price')
                    ->get();

                foreach ($rows as $row) {
                    DB::table('store_product')
                        ->where('id', $row->pivot_id)
                        ->update(['custom_price' => round((float) $row->product_price * $storeMarkup, 2)]);
                }
                $updatedStores++;
            }
        });

        $this->info("Synced {$synced}. Failed: {$failed}. Linked: {$linked}. Sellable: {$sellable}. Unsellable: {$unsellable}. Stores refreshed: {$updatedStores}. Categories updated: {$categoriesUpdated}.");

        return 0;
    }

    private function syncCategories(OranosMarketService $service): void
    {
        try {
            $categories = $service->getCategories();
        } catch (\Throwable $e) {
            $this->warn('Categories endpoint not available, falling back to product-based category creation: '.$e->getMessage());

            return;
        }

        $synced = 0;
        $categoriesUpdated = 0;

        foreach ($categories as $categoryData) {
            try {
                $oranosId = $categoryData['id'] ?? null;
                $name = $categoryData['name'] ?? null;
                $parentId = $categoryData['parent_id'] ?? null;

                if (! $name) {
                    continue;
                }

                // Extract Oranos category image
                $rawImg = $categoryData['image'] ?? $categoryData['image_url'] ?? $categoryData['img'] ?? $categoryData['image_base64'] ?? null;
                $categoryImg = null;
                $categoryImgBase64 = null;
                if (is_string($rawImg) && $rawImg !== '' && ! str_contains($rawImg, 'empty.png') && str_contains($rawImg, '/images/')) {
                    if (str_starts_with($rawImg, 'data:image/') || str_starts_with($rawImg, 'data:application/')) {
                        $categoryImgBase64 = $rawImg;
                    } else {
                        $categoryImg = $rawImg;
                    }
                }

                // Match by name_ar/name first, then by oranos_category_id
                $category = Category::where('name_ar', $name)
                    ->orWhere('name', $name)
                    ->first();

                if (! $category && $oranosId) {
                    $category = Category::where('oranos_category_id', $oranosId)->first();
                }

                if (! $category) {
                    $slug = Str::slug($name);
                    if (empty($slug)) {
                        $slug = 'cat-'.substr(md5($name), 0, 12);
                    }

                    // Ensure slug is unique by appending oranos_id if needed
                    if ($oranosId && Category::where('slug', $slug)->exists()) {
                        $slug = $slug.'-'.$oranosId;
                    }

                    $category = Category::create([
                        'slug' => $slug,
                        'name' => $name,
                        'name_ar' => $name,
                        'type' => 'auto',
                        'icon' => 'package',
                    ]);
                }

                // Store Oranos category ID for parent linking
                if ($oranosId && ! $category->oranos_category_id) {
                    $category->update(['oranos_category_id' => $oranosId]);
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

                // Handle parent category linking
                if ($parentId) {
                    $parent = Category::where('oranos_category_id', $parentId)->first();
                    if ($parent && $category->parent_id !== $parent->id) {
                        $category->update(['parent_id' => $parent->id]);
                    }
                }

                $synced++;
            } catch (\Throwable $e) {
                Log::warning('Failed to sync Oranos category', ['oranos_id' => $categoryData['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Pre-synced {$synced} categories from Oranos API. Categories updated: {$categoriesUpdated}.");
    }

    /**
     * Find or create a category by name, with fallback to oranos_category_id.
     * Avoids creating duplicate categories for Arabic names by matching on name_ar/name first.
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
