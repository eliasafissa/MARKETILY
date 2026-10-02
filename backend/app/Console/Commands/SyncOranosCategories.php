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

    protected $description = 'Sync categories from Oranos Market API (root + subcategories)';

    public function handle(OranosMarketService $service): int
    {
        // 1. Fetch root categories from /client/api/content/0
        try {
            $rootData = $service->getContent(0);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch Oranos root categories', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch categories: '.$e->getMessage());
            return 1;
        }

        $rootCategories = $rootData['categories'] ?? [];
        $this->info('Root categories: ' . count($rootCategories));

        $synced = 0;
        $skipped = 0;
        $withChildren = 0;

        // 2. Sync each root category
        foreach ($rootCategories as $catData) {
            $this->syncCategory($catData, null);
            $synced++;

            // 3. Fetch subcategories for this root
            $oranosId = $catData['id'] ?? null;
            if (! $oranosId) {
                continue;
            }

            try {
                $subData = $service->getContent((int) $oranosId);
            } catch (\Throwable $e) {
                $this->warn("Failed to fetch subcategories for #{$oranosId}: " . $e->getMessage());
                continue;
            }

            $subCategories = $subData['categories'] ?? [];
            $parentLocal = Category::where('oranos_category_id', $oranosId)->first();

            foreach ($subCategories as $subCat) {
                $this->syncCategory($subCat, $parentLocal?->id);
                $withChildren++;
            }

            if (count($subCategories) > 0) {
                $this->line("  #{$oranosId} ({$catData['name']}): " . count($subCategories) . " subcategories");
            }
        }

        $this->info("Done. Synced {$synced} root + {$withChildren} subcategories. Skipped: {$skipped}.");

        return 0;
    }

    /**
     * Sync a single category (create or update).
     */
    private function syncCategory(array $catData, ?int $parentId): ?Category
    {
        $oranosId = $catData['id'] ?? null;
        $name = trim((string) ($catData['name'] ?? ''));

        // Skip invalid categories (e.g. "null" name or empty id)
        if (! $oranosId || $name === '' || strtolower($name) === 'null') {
            return null;
        }

        try {
            // Find by oranos_category_id first, then by name
            $category = Category::where('oranos_category_id', $oranosId)->first();

            if (! $category) {
                $category = Category::where('name_ar', $name)->orWhere('name', $name)->first();
            }

            if ($category) {
                // Update existing
                $update = [];
                if (! $category->oranos_category_id) {
                    $update['oranos_category_id'] = $oranosId;
                }
                if ($parentId !== null && $category->parent_id !== $parentId) {
                    $update['parent_id'] = $parentId;
                }
                if ($category->name_ar !== $name) {
                    $update['name_ar'] = $name;
                }
                if (! empty($update)) {
                    $category->update($update);
                }
                return $category;
            }

            // Create new
            $slug = Str::slug($name);
            if (empty($slug)) {
                $slug = 'cat-' . $oranosId;
            }
            $base = $slug;
            $i = 2;
            while (Category::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i;
                $i++;
            }

            return Category::create([
                'oranos_category_id' => $oranosId,
                'parent_id'          => $parentId,
                'name'               => $name,
                'name_ar'            => $name,
                'slug'               => $slug,
                'type'               => 'auto',
                'icon'               => 'package',
                'sort_order'         => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync category', [
                'oranos_id' => $oranosId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}