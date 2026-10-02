<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportOranosCategoryImages extends Command
{
    protected $signature = 'oranos:import-category-images';
    protected $description = 'Import category images from Oranos category products API';

    public function handle(): int
    {
        $baseUrl = rtrim(config('services.oranos.url'), '/');
        $token = config('services.oranos.token');

        $categories = Category::whereNotNull('oranos_category_id')->get();
        $this->info("Found {$categories->count()} categories with Oranos IDs");

        $updated = 0;
        $skipped = 0;
        $noPhoto = 0;
        $errors = 0;

        foreach ($categories as $index => $category) {
            $oid = $category->oranos_category_id;

            if ($index > 0 && $index % 10 === 0) {
                $this->line("Progress: {$index}/{$categories->count()} (Updated: {$updated}, Skipped: {$skipped}, NoPhoto: {$noPhoto}, Errors: {$errors})");
            }

            try {
                $response = Http::withHeader('api-token', $token)
                    ->timeout(120)
                    ->connectTimeout(30)
                    ->get("{$baseUrl}/api/category/products/{$oid}?lang=ar");

                if (! $response->successful()) {
                    $this->warn("  [{$index}] #{$oid} HTTP {$response->status()}");
                    $errors++;
                    continue;
                }

                $data = $response->json();
                $products = $data['data']['products'] ?? [];

                if (empty($products)) {
                    $noPhoto++;
                    continue;
                }

                $catPhoto = $products[0]['category_photo'] ?? null;

                if (! $catPhoto) {
                    $noPhoto++;
                    continue;
                }

                if (! str_starts_with($catPhoto, 'http')) {
                    $catPhoto = "https://api.oranosmarket.com/{$catPhoto}";
                }

                if (str_contains($catPhoto, 'empty.png')) {
                    $skipped++;
                    continue;
                }

                $current = $category->image_url;
                if (empty($current) || $current === 'https://api.oranosmarket.com/' || ! str_contains($current, '/images/category/')) {
                    $category->update(['image_url' => $catPhoto]);
                    $updated++;
                    $this->line("  ✓ [{$index}] #{$oid} {$category->name} => {$catPhoto}");
                } else {
                    $skipped++;
                }

            } catch (\Throwable $e) {
                $this->warn("  [{$index}] #{$oid} Error: {$e->getMessage()}");
                Log::error('Failed to import category image', ['category_id' => $oid, 'error' => $e->getMessage()]);
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Done. Updated: {$updated}  Skipped: {$skipped}  NoPhoto: {$noPhoto}  Errors: {$errors}");

        return 0;
    }
}
