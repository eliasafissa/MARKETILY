<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncOranosProducts extends Command
{
    protected $signature = 'oranos:sync-products';
    protected $description = 'Sync products from Oranos Market API';

    public function handle(OranosMarketService $service): int
    {
        try {
            $products = $service->getProducts();
        } catch (\Throwable $e) {
            Log::error('Oranos getProducts failed', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch products: '.$e->getMessage());
            return 1;
        }

        $this->info('Fetched ' . count($products) . ' products from Oranos.');

        $markupPercent = (float) Setting::get('oranos_markup_percent', 20);
        $markup = 1 + ($markupPercent / 100);
        $this->info("Using markup: {$markupPercent}%");

        $synced = 0;
        $updated = 0;
        $failed = 0;
        $inactive = 0;
        $noCategory = 0;

        foreach ($products as $product) {
            try {
                $oranosId = $product['id'] ?? null;
                if (! $oranosId) {
                    continue;
                }

                $name = trim((string) ($product['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $oranosPrice = (float) ($product['price'] ?? 0);
                $ourRetail = round($oranosPrice * $markup, 4);

                // Availability
                $available = $product['available'] ?? true;
                if (is_string($available)) {
                    $available = in_array(strtolower($available), ['active', 'available', 'in_stock', 'true', '1']);
                }

                $isActive = $available && $oranosPrice > 0;

                // Params — array of strings (labels for input fields)
                $params = $product['params'] ?? null;
                if (! is_array($params)) {
                    $params = [];
                }

                // qty_values — null | {min, max} | [list]
                $qtyValues = $product['qty_values'] ?? null;

                // Product type — 'amount' or 'package'
                $productType = $product['product_type'] ?? 'package';

                // Parent category — Oranos uses parent_id = the category id
                $oranosParentId = $product['parent_id'] ?? null;

                // Category image (fallback for product image)
                $categoryImg = $product['category_img'] ?? null;
                if (is_string($categoryImg) && str_contains($categoryImg, 'empty.png')) {
                    $categoryImg = null;
                }

                // Find local category by oranos_category_id (parent_id maps to the category)
                $category = null;
                if ($oranosParentId) {
                    $category = Category::where('oranos_category_id', $oranosParentId)->first();
                }

                // Fallback: match by category_name
                if (! $category && ! empty($product['category_name'])) {
                    $catName = trim((string) $product['category_name']);
                    $category = Category::where('name_ar', $catName)
                        ->orWhere('name', $catName)
                        ->first();
                }

                if (! $category) {
                    $noCategory++;
                    continue;
                }

                // Find existing product by oranos_product_id
                $existing = Product::where('oranos_product_id', $oranosId)->first();

                if ($existing) {
                    $existing->update([
                        'category_id'      => $category->id,
                        'name'             => $name,
                        'name_ar'          => $name,
                        'description'      => $name,
                        'description_ar'   => $name,
                        'base_price'       => $oranosPrice,
                        'price'            => $ourRetail,
                        'is_automation'    => true,
                        'qty_values'       => $qtyValues,
                        'params'           => $params,
                        'is_active'        => $isActive,
                        'oranos_available' => $available,
                        'stock'            => $available ? 999 : 0,
                        'image_url'        => $categoryImg ?: $existing->image_url,
                    ]);
                    $updated++;
                } else {
                    $slug = Str::slug($name) . '-' . $oranosId;
                    if (empty($slug)) {
                        $slug = 'product-' . $oranosId;
                    }
                    // Ensure slug uniqueness
                    $base = $slug;
                    $i = 2;
                    while (Product::where('slug', $slug)->exists()) {
                        $slug = $base . '-' . $i;
                        $i++;
                    }

                    Product::create([
                        'oranos_product_id' => $oranosId,
                        'category_id'       => $category->id,
                        'name'              => $name,
                        'name_ar'           => $name,
                        'slug'              => $slug,
                        'description'       => $name,
                        'description_ar'    => $name,
                        'base_price'        => $oranosPrice,
                        'price'             => $ourRetail,
                        'type'              => 'auto',
                        'is_automation'     => true,
                        'qty_values'        => $qtyValues,
                        'params'            => $params,
                        'is_active'         => $isActive,
                        'oranos_available'  => $available,
                        'stock'             => $available ? 999 : 0,
                        'image_url'         => $categoryImg ?: null,
                    ]);
                    $synced++;
                }

                if (! $isActive) {
                    $inactive++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Failed to sync Oranos product', [
                    'oranos_id' => $product['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('=== SYNC COMPLETE ===');
        $this->info("New:        {$synced}");
        $this->info("Updated:    {$updated}");
        $this->info("Inactive:   {$inactive}");
        $this->info("No category:{$noCategory}");
        $this->info("Failed:     {$failed}");

        return 0;
    }
}
