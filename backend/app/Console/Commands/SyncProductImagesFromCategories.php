<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class SyncProductImagesFromCategories extends Command
{
    protected $signature = 'oranos:sync-product-images';
    protected $description = 'Sync product images from their category images';

    public function handle(): int
    {
        $updated = 0;
        $skipped = 0;
        $noCategoryImage = 0;

        Product::whereNull('image_url')
            ->orWhere('image_url', '')
            ->orWhere('image_url', 'https://api.oranosmarket.com/')
            ->chunk(500, function ($products) use (&$updated, &$skipped, &$noCategoryImage) {
                foreach ($products as $product) {
                    $category = $product->category;
                    if ($category && $category->image_url && $category->image_url !== '') {
                        $product->update(['image_url' => $category->image_url]);
                        $updated++;
                    } else {
                        $noCategoryImage++;
                    }
                }
            });

        // Also update products that have the placeholder
        Product::where('image_url', 'https://api.oranosmarket.com/')
            ->chunk(500, function ($products) use (&$updated, &$skipped, &$noCategoryImage) {
                foreach ($products as $product) {
                    $category = $product->category;
                    if ($category && $category->image_url && $category->image_url !== '') {
                        $product->update(['image_url' => $category->image_url]);
                        $updated++;
                    } else {
                        $noCategoryImage++;
                    }
                }
            });

        $this->newLine();
        $this->info("Updated: {$updated}. No category image: {$noCategoryImage}.");

        return 0;
    }
}
