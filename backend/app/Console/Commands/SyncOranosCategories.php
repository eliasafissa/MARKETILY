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
    protected $description = 'Recursively sync the full Oranos category tree (all levels)';

    protected OranosMarketService $service;
    protected array $processed = [];

    public function handle(OranosMarketService $service): int
    {
        $this->service = $service;

        $this->info('Fetching root tree from Oranos...');
        $root = $service->getContent(0);

        $rootCategories = $root['categories'] ?? [];
        $this->info('Root categories: ' . count($rootCategories));

        $rootCount = 0;
        $subCount = 0;

        foreach ($rootCategories as $catData) {
            $oranosId = $catData['id'] ?? null;
            $name = trim((string) ($catData['name'] ?? ''));

            if (! $oranosId || $name === '' || strtolower($name) === 'null') {
                continue;
            }

            // 1. Create/update root category
            $category = $this->upsertCategory($catData, null);
            if (! $category) continue;

            $rootCount++;
            $this->line("  Root #{$oranosId} ({$name})");

            // 2. Recursively fetch subcategories + products
            $subCount += $this->syncChildren((int) $oranosId, $category->id);
        }

        $this->newLine();
        $this->info('=== SYNC COMPLETE ===');
        $this->info("Root categories:       {$rootCount}");
        $this->info("Subcategories (total): {$subCount}");
        $this->info('Total categories:      ' . Category::count());

        return 0;
    }

    /**
     * Recursively fetch content/{id} and create subcategories.
     */
    protected function syncChildren(int $oranosId, int $parentLocalId): int
    {
        // Prevent infinite loops
        if (in_array($oranosId, $this->processed, true)) {
            return 0;
        }
        $this->processed[] = $oranosId;

        try {
            $data = $this->service->getContent($oranosId);
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch children for Oranos #{$oranosId}", [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        $children = $data['categories'] ?? [];
        $count = 0;

        foreach ($children as $childData) {
            $childOranosId = $childData['id'] ?? null;
            $childName = trim((string) ($childData['name'] ?? ''));

            if (! $childOranosId || $childName === '' || strtolower($childName) === 'null') {
                continue;
            }

            // Create/update the child with the correct parent_id
            $child = $this->upsertCategory($childData, $parentLocalId);
            if (! $child) continue;

            $count++;
            $this->line("    └── #{$childOranosId} ({$childName})");

            // Recurse — this child might have its own children
            $count += $this->syncChildren((int) $childOranosId, $child->id);
        }

        return $count;
    }

    /**
     * Create or update a category with the given parent local id.
     */
    protected function upsertCategory(array $data, ?int $parentLocalId): ?Category
    {
        $oranosId = $data['id'] ?? null;
        $name = trim((string) ($data['name'] ?? ''));

        if (! $oranosId || $name === '' || strtolower($name) === 'null') {
            return null;
        }

        try {
            // Try by oranos_category_id first (most reliable)
            $category = Category::where('oranos_category_id', $oranosId)->first();

            if (! $category) {
                // Try by exact name + parent_id combination
                $category = Category::where('name', $name)
                    ->where(function ($q) use ($parentLocalId) {
                        if ($parentLocalId === null) {
                            $q->whereNull('parent_id');
                        } else {
                            $q->where('parent_id', $parentLocalId);
                        }
                    })
                    ->first();
            }

            if ($category) {
                // Update existing
                $updates = [];
                if (! $category->oranos_category_id) {
                    $updates['oranos_category_id'] = $oranosId;
                }
                if ($category->parent_id !== $parentLocalId) {
                    $updates['parent_id'] = $parentLocalId;
                }
                if ($category->name_ar !== $name) {
                    $updates['name_ar'] = $name;
                }
                if (! empty($updates)) {
                    $category->update($updates);
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
                $slug = $base . '-' . $i++;
            }

            return Category::create([
                'oranos_category_id' => $oranosId,
                'parent_id'          => $parentLocalId,
                'name'               => $name,
                'name_ar'            => $name,
                'slug'               => $slug,
                'type'               => 'auto',
                'icon'               => 'package',
                'sort_order'         => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to upsert category', [
                'oranos_id' => $oranosId,
                'name' => $name,
                'parent_local' => $parentLocalId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
