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
    protected $description = 'Sync products from Oranos and link them to their categories';

    protected OranosMarketService $service;
    protected array $categoriesByOranosId = [];
    protected array $categoriesByName = [];

    public function handle(OranosMarketService $service): int
    {
        $this->service = $service;

        try {
            $products = $service->getProducts();
        } catch (\Throwable $e) {
            Log::error('Oranos getProducts failed', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch products: '.$e->getMessage());
            return 1;
        }

        $this->info('Fetched ' . count($products) . ' products from Oranos.');

        // Preload all categories into memory
        $this->preloadCategories();

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
                if (! $oranosId) continue;

                $name = trim((string) ($product['name'] ?? ''));
                if ($name === '') continue;

                $oranosPrice = (float) ($product['price'] ?? 0);
                $ourRetail = round($oranosPrice * $markup, 4);

                $available = $product['available'] ?? true;
                if (is_string($available)) {
                    $available = in_array(strtolower($available), ['active', 'available', 'in_stock', 'true', '1']);
                }
                $isActive = $available && $oranosPrice > 0;

                $params = is_array($product['params'] ?? null) ? $product['params'] : [];
                $qtyValues = $product['qty_values'] ?? null;

                $oranosParentId = $product['parent_id'] ?? null;
                $categoryName = trim((string) ($product['category_name'] ?? ''));

                $categoryImg = $product['category_img'] ?? null;
                if (is_string($categoryImg) && str_contains($categoryImg, 'empty.png')) {
                    $categoryImg = null;
                }

                // Resolve category — must already exist (created by sync-categories)
                $category = null;
                if ($oranosParentId && isset($this->categoriesByOranosId[$oranosParentId])) {
                    $category = $this->categoriesByOranosId[$oranosParentId];
                } elseif ($categoryName !== '' && isset($this->categoriesByName[$categoryName])) {
                    $category = $this->categoriesByName[$categoryName];
                }

                if (! $category) {
                    // No category found — skip (categories must be synced first)
                    $noCategory++;
                    continue;
                }

                $existing = Product::where('oranos_product_id', $oranosId)->first();

                $payload = [
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
                ];

                if ($categoryImg) {
                    $payload['image_url'] = $categoryImg;
                } elseif (! $existing?->image_url) {
                    $payload['image_url'] = null;
                }

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    $slug = Str::slug($name) . '-' . $oranosId;
                    if (empty($slug)) $slug = 'product-' . $oranosId;
                    $base = $slug; $i = 2;
                    while (Product::where('slug', $slug)->exists()) {
                        $slug = $base . '-' . $i++;
                    }

                    $payload['slug'] = $slug;
                    $payload['oranos_product_id'] = $oranosId;
                    $payload['type'] = 'auto';

                    Product::create($payload);
                    $synced++;
                }

                if (! $isActive) $inactive++;
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
        $this->info("New products:      {$synced}");
        $this->info("Updated products:  {$updated}");
        $this->info("Inactive:          {$inactive}");
        $this->info("No category:       {$noCategory}");
        $this->info("Failed:            {$failed}");

        return 0;
    }

    protected function preloadCategories(): void
    {
        Category::query()->chunk(500, function ($chunk) {
            foreach ($chunk as $cat) {
                if ($cat->oranos_category_id) {
                    $this->categoriesByOranosId[$cat->oranos_category_id] = $cat;
                }
                if ($cat->name) {
                    $this->categoriesByName[$cat->name] = $cat;
                }
                if ($cat->name_ar && $cat->name_ar !== $cat->name) {
                    $this->categoriesByName[$cat->name_ar] = $cat;
                }
            }
        });
    }
}
