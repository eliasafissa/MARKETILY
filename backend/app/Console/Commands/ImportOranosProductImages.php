<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportOranosProductImages extends Command
{
    protected $signature = 'oranos:import-product-images';
    protected $description = 'Import product images from Oranos category products API';

    public function handle(): int
    {
        $baseUrl = rtrim(config('services.oranos.url'), '/');
        $token = config('services.oranos.token');

        $categories = \App\Models\Category::whereNotNull('oranos_category_id')->get();
        $this->info("Found {$categories->count()} categories with Oranos IDs");

        $totalProducts = 0;
        $updated = 0;
        $skipped = 0;
        $notLocal = 0;
        $errors = 0;

        foreach ($categories as $category) {
            $oid = $category->oranos_category_id;
            $this->line("Fetching products for category #{$oid} ({$category->name})...");

            try {
                $response = Http::withHeader('api-token', $token)
                    ->timeout(120)
                    ->connectTimeout(30)
                    ->get("{$baseUrl}/api/category/products/{$oid}?lang=ar");

                if (! $response->successful()) {
                    $this->warn("  Failed: HTTP {$response->status()}");
                    $errors++;
                    continue;
                }

                $data = $response->json();
                $products = $data['data']['products'] ?? [];

                if (empty($products)) {
                    $this->line("  No products");
                    continue;
                }

                foreach ($products as $p) {
                    $totalProducts++;
                    $oranosId = $p['id'] ?? null;
                    $photo = $p['photo'] ?? null;

                    if (! $oranosId || ! $photo) {
                        $skipped++;
                        continue;
                    }

                    if (! str_starts_with($photo, 'http')) {
                        $photo = "https://api.oranosmarket.com/{$photo}";
                    }

                    if (str_contains($photo, 'empty.png')) {
                        $skipped++;
                        continue;
                    }

                    $product = Product::where('oranos_product_id', $oranosId)->first();
                    if (! $product) {
                        $notLocal++;
                        continue;
                    }

                    // Update if image is empty, placeholder, or not a product image
                    $current = $product->image_url;
                    if (empty($current) || $current === 'https://api.oranosmarket.com/' || ! str_contains($current, '/images/product/')) {
                        $product->update(['image_url' => $photo]);
                        $updated++;
                        $this->line("  ✓ #{$oranosId} => {$photo}");
                    } else {
                        $skipped++;
                    }
                }

            } catch (\Throwable $e) {
                $this->warn("  Error: {$e->getMessage()}");
                Log::error('Failed to import product images', ['category_id' => $oid, 'error' => $e->getMessage()]);
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Total products processed: {$totalProducts}");
        $this->info("Updated: {$updated}  Skipped: {$skipped}  NotLocal: {$notLocal}  Errors: {$errors}");

        return 0;
    }
}
