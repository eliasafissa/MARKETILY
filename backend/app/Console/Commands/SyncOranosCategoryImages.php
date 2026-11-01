<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Support\OranosImageUrl;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;

/**
 * The documented Oranos product feed contains category_img, not a distinct
 * product photo. Reuse that as category art, never claim it is a product photo.
 */
class SyncOranosCategoryImages extends Command
{
    protected $signature = 'oranos:sync-category-images {--dry-run : Show changes without writing}';
    protected $description = 'Import category_img from the documented Oranos product feed without overriding manual category images';

    public function handle(OranosMarketService $service): int
    {
        try {
            $products = $service->getProducts();
        } catch (\Throwable $e) {
            $this->error('Unable to fetch Oranos products: ' . $e->getMessage());
            return self::FAILURE;
        }

        $categories = Category::whereNotNull('oranos_category_id')->get()->keyBy('oranos_category_id');
        $mapped = [];
        $noImage = 0;
        $unmatched = 0;
        $invalid = 0;

        foreach ($products as $product) {
            $categoryId = $product['parent_id'] ?? null;
            $value = $product['category_img'] ?? null;
            if (! is_numeric($categoryId) || ! $categories->has((int) $categoryId)) {
                $unmatched++;
                continue;
            }
            if (! is_string($value) || trim($value) === '' || str_contains(strtolower($value), 'empty.png')) {
                $noImage++;
                continue;
            }
            $url = OranosImageUrl::normalize($value);
            if (! $url) {
                $invalid++;
                continue;
            }
            $mapped[(int) $categoryId] ??= $url;
        }

        $updated = 0;
        $preserved = 0;
        foreach ($mapped as $id => $url) {
            $category = $categories->get($id);
            if (! $category) continue;
            // Refresh provider fallback independently; admin image still wins at display time.
            if ($category->oranos_image_url === $url) {
                $preserved++;
                continue;
            }
            if (! $this->option('dry-run')) {
                $category->update(['oranos_image_url' => $url]);
            }
            $updated++;
        }

        $this->info(($this->option('dry-run') ? 'DRY RUN' : 'COMPLETE') . ': category images ' . $updated);
        $this->line("Preserved existing: {$preserved}; feed without image: {$noImage}; unmatched products: {$unmatched}; unsafe/invalid URLs: {$invalid}");
        $this->warn('The documented API does not guarantee an individual photo for every product or category.');
        return self::SUCCESS;
    }

}
