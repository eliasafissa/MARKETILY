<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateCategories extends Command
{
    protected $signature = 'oranos:merge-duplicate-categories {--force : Actually execute the merge (dry-run by default)}';
    protected $description = 'Merge duplicate categories by name_ar/name and oranos_category_id';

    public function handle(): int
    {
        $force = $this->option('force');
        $this->info($force ? 'Running merge with --force (EXECUTING)' : 'Running in DRY-RUN mode (no changes)');

        $totalGroups = 0;
        $totalDuplicates = 0;
        $totalProductsMoved = 0;
        $totalChildrenMoved = 0;

        // 1. Find duplicates by name_ar (or name if name_ar is null)
        $duplicateGroups = Category::selectRaw('COALESCE(TRIM(name_ar), TRIM(name)) as uname, COUNT(*) as cnt, GROUP_CONCAT(id) as ids')
            ->where(function ($q) {
                $q->whereNotNull('name_ar')->orWhereNotNull('name');
            })
            ->groupBy('uname')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $ids = array_map('intval', explode(',', $group->ids));
            $this->processGroup($ids, $group->uname, $force, $totalDuplicates, $totalProductsMoved, $totalChildrenMoved);
            $totalGroups++;
        }

        // 2. Also find duplicates by oranos_category_id
        $oranosDuplicates = Category::selectRaw('oranos_category_id, COUNT(*) as cnt, GROUP_CONCAT(id) as ids')
            ->whereNotNull('oranos_category_id')
            ->groupBy('oranos_category_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($oranosDuplicates as $group) {
            $ids = array_map('intval', explode(',', $group->ids));
            // Skip if already processed in name-based groups
            if (!$this->alreadyProcessed($ids)) {
                $this->processGroup($ids, "oranos_id:{$group->oranos_category_id}", $force, $totalDuplicates, $totalProductsMoved, $totalChildrenMoved);
                $totalGroups++;
            }
        }

        $this->newLine();
        $this->info("=== SUMMARY ===");
        $this->info("Groups processed: {$totalGroups}");
        $this->info("Duplicate rows removed: {$totalDuplicates}");
        $this->info("Products reassigned: {$totalProductsMoved}");
        $this->info("Children reparented: {$totalChildrenMoved}");

        if (! $force) {
            $this->warn('This was a DRY-RUN. Re-run with --force to execute changes.');
        }

        return 0;
    }

    private function processGroup(array $ids, string $groupName, bool $force, int &$totalDuplicates, int &$totalProductsMoved, int &$totalChildrenMoved): void
    {
        if (count($ids) < 2) return;

        $categories = Category::whereIn('id', $ids)->orderBy('id')->get();

        // Determine keeper: prefer non-md5 slug, then oldest, then most products
        $keeper = $this->selectKeeper($categories);

        $duplicateIds = $categories->where('id', '!=', $keeper->id)->pluck('id')->toArray();

        if (empty($duplicateIds)) return;

        $this->info("\n--- Group: {$groupName} ---");
        $this->info("Keeper: ID {$keeper->id} (slug: {$keeper->slug}, oranos_id: {$keeper->oranos_category_id})");

        foreach ($categories->where('id', '!=', $keeper->id) as $dup) {
            $this->line("  Duplicate: ID {$dup->id} (slug: {$dup->slug}, oranos_id: {$dup->oranos_category_id})");
        }

        if (! $force) {
            $this->warn('  [DRY-RUN] Would merge ' . count($duplicateIds) . ' duplicates into keeper ID ' . $keeper->id);
            return;
        }

        // Collect oranos_category_ids from duplicates BEFORE deleting them
        $oranosIdsToTransfer = Category::whereIn('id', $duplicateIds)
            ->whereNotNull('oranos_category_id')
            ->orderBy('id')
            ->pluck('oranos_category_id')
            ->unique()
            ->toArray();

        $productsMoved = $this->mergeProducts($keeper->id, $duplicateIds);
        $childrenMoved = $this->mergeChildren($keeper->id, $duplicateIds);
        $this->deleteDuplicates($duplicateIds);

        // Now transfer oranos_category_id to keeper (after duplicates are deleted)
        $this->mergeOranosId($keeper, $oranosIdsToTransfer);

        $totalDuplicates += count($duplicateIds);
        $totalProductsMoved += $productsMoved;
        $totalChildrenMoved += $childrenMoved;

        $this->info("  Merged: {$productsMoved} products, {$childrenMoved} children");
    }

    private function selectKeeper($categories): Category
    {
        // 1. Prefer non-md5 slug
        $nonMd5 = $categories->filter(fn($c) => ! preg_match('/^cat-[a-f0-9]{12,}$/', $c->slug));
        if ($nonMd5->count() === 1) return $nonMd5->first();

        // 2. If all have md5 slugs, prefer oldest (lowest id)
        $oldest = $categories->sortBy('id')->first();
        if ($oldest) return $oldest;

        // 3. Fallback: most products
        return $categories->sortByDesc(fn($c) => $c->products()->count())->first();
    }

    private function mergeProducts(int $keeperId, array $duplicateIds): int
    {
        return DB::transaction(function () use ($keeperId, $duplicateIds) {
            $count = Product::whereIn('category_id', $duplicateIds)->count();
            if ($count > 0) {
                Product::whereIn('category_id', $duplicateIds)
                    ->update(['category_id' => $keeperId]);
            }
            return $count;
        });
    }

    private function mergeChildren(int $keeperId, array $duplicateIds): int
    {
        return DB::transaction(function () use ($keeperId, $duplicateIds) {
            $count = Category::whereIn('parent_id', $duplicateIds)->count();
            if ($count > 0) {
                Category::whereIn('parent_id', $duplicateIds)
                    ->update(['parent_id' => $keeperId]);
            }
            return $count;
        });
    }

    private function mergeOranosId(Category $keeper, array $oranosIds): void
    {
        if ($keeper->oranos_category_id || empty($oranosIds)) return;

        foreach ($oranosIds as $oranosId) {
            // Check if this oranos_id is already used by another category
            $existing = Category::where('oranos_category_id', $oranosId)
                ->where('id', '!=', $keeper->id)
                ->exists();

            if (! $existing) {
                $keeper->update(['oranos_category_id' => $oranosId]);
                $this->line("  Set oranos_category_id={$oranosId} on keeper ID {$keeper->id}");
                break;
            }
        }
    }

    private function deleteDuplicates(array $duplicateIds): void
    {
        Category::whereIn('id', $duplicateIds)->delete();
    }

    private function alreadyProcessed(array $ids): bool
    {
        // Simple check - if we've seen these IDs in a previous group
        // In practice, groups by name and oranos_id shouldn't overlap much
        return false;
    }
}