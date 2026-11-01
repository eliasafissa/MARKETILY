<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\OranosMarketService;
use App\Support\OranosImageUrl;
use Illuminate\Console\Command;

class RepairOranosImageLinks extends Command
{
    protected $signature = 'oranos:repair-image-links {--apply : Apply changes (requires a database backup first)}';
    protected $description = 'Audit Oranos image link paths and optionally repair only provider image fields';

    public function handle(OranosMarketService $service): int
    {
        try {
            $feed = $service->getProducts();
        } catch (\Throwable $e) {
            $this->error('Cannot read Oranos image feed: '.$e->getMessage());
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $available = [];
        $sourcesWithoutImagePath = 0;
        foreach ($feed as $item) {
            $id = $item['id'] ?? null;
            $url = OranosImageUrl::normalize($item['category_img'] ?? null);
            if ($url === null) {
                $sourcesWithoutImagePath++;
            }
            if ($id !== null && $url !== null) {
                $available[(string) $id] = [
                    'url' => $url,
                    'parent_id' => (int) ($item['parent_id'] ?? 0),
                ];
            }
        }

        $productsChanged = 0;
        $categoriesChanged = 0;
        $productsWithoutImage = 0;
        $localCategoryIds = Category::whereNotNull('oranos_category_id')
            ->get(['id', 'oranos_category_id'])->keyBy('oranos_category_id');
        $categoryImage = [];

        Product::whereNotNull('oranos_product_id')
            ->orderBy('id')->chunkById(300, function ($products) use (
                &$productsChanged, &$productsWithoutImage,
                $available, &$categoryImage, $localCategoryIds, $apply
            ) {
                foreach ($products as $product) {
                    $key = (string) $product->oranos_product_id;
                    $record = $available[$key] ?? null;
                    $desiredUrl = $record['url'] ?? null;
                    $existing = $product->getRawOriginal('oranos_image_url');
                    if ($desiredUrl === null) {
                        $productsWithoutImage++;
                    }
                    // Never replace a valid previously-stored picture if the feed has no image.
                    // Only remove known bogus API-homepage placeholders.
                    $replacement = $desiredUrl ?? (OranosImageUrl::normalize($existing) ?? (OranosImageUrl::isEmptyApiUrl($existing) ? null : $existing));
                    if ($replacement !== $existing) {
                        if ($apply) {
                            $product->update(['oranos_image_url' => $replacement]);
                        }
                        $productsChanged++;
                    }
                    if ($record && $localCategoryIds->has($record['parent_id'])) {
                        $categoryImage[$record['parent_id']] ??= $record['url'];
                    }
                }
            });

        Category::whereNotNull('oranos_category_id')
            ->orderBy('id')->chunkById(300, function ($categories) use ($categoryImage, &$categoriesChanged, $apply) {
                foreach ($categories as $category) {
                    $existing = $category->getRawOriginal('oranos_image_url');
                    $desired = $categoryImage[$category->oranos_category_id] ?? null;
                    $replacement = $desired ?? (OranosImageUrl::normalize($existing) ?? (OranosImageUrl::isEmptyApiUrl($existing) ? null : $existing));
                    if ($existing !== $replacement) {
                        if ($apply) {
                            $category->update(['oranos_image_url' => $replacement]);
                        }
                        $categoriesChanged++;
                    }
                }
            });

        $this->line('Oranos feed products: '.count($feed));
        $this->line('Feed entries without documented image file: '.$sourcesWithoutImagePath);
        $this->line('Local products without usable fresh feed image: '.$productsWithoutImage);
        $this->line('Local products needing provider-link repair: '.$productsChanged);
        $this->line('Local categories needing provider-link repair: '.$categoriesChanged);
        $this->info($apply ? 'IMAGE LINK REPAIR COMPLETED' : 'DRY RUN ONLY - NO DATABASE CHANGES');
        if ($sourcesWithoutImagePath === count($feed)) {
            $this->warn('Oranos returned no usable image paths in category_img. Genuine photos cannot be imported from this feed.');
        }
        $this->line('Admin image overrides and prices were not modified.');
        return self::SUCCESS;
    }
}
