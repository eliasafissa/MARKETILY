<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Read-only: verifies provider feed and local mappings. No catalog writes. */
class AuditOranosCatalog extends Command
{
    protected $signature = 'marketly:audit-oranos-catalog {--live : Request live Oranos product feed (read-only)}';
    protected $description = 'Inspect catalog coverage, empty categories, inactive products and provider category mapping';

    public function handle(OranosMarketService $oranos): int
    {
        $this->info('MARKETLY ORANOS CATALOG AUDIT (READ ONLY)');
        $categories = Category::query()->get(['id', 'name', 'oranos_category_id', 'parent_id']);
        $products = Product::query()->get(['id', 'category_id', 'oranos_product_id', 'is_active', 'base_price']);
        $categoryIds = $categories->pluck('id')->flip();
        $this->line('LOCAL CATEGORY ROWS: '.$categories->count());
        $this->line('LOCAL PRODUCT ROWS: '.$products->count());
        $this->line('LOCAL ORANOS PRODUCT ROWS: '.$products->filter(fn ($p) => $p->oranos_product_id !== null)->count());
        $this->line('LOCAL ACTIVE PRODUCT ROWS: '.$products->where('is_active', true)->count());
        $this->line('LOCAL INACTIVE PRODUCT ROWS: '.$products->where('is_active', false)->count());
        $this->line('PRODUCTS WITH INVALID PRICE: '.$products->filter(fn ($p) => (float) $p->base_price <= 0)->count());
        $this->line('PRODUCTS WITH MISSING CATEGORY: '.$products->filter(fn ($p) => !$categoryIds->has($p->category_id))->count());
        $parents = $categories->pluck('parent_id')->filter()->unique()->flip();
        $direct = $products->where('is_active', true)->groupBy('category_id');
        $emptyLeaves = $categories->filter(fn ($cat) => !$parents->has($cat->id) && !$direct->has($cat->id));
        $this->line('EMPTY LEAF CATEGORIES (NO ACTIVE PRODUCTS, NO CHILDREN): '.$emptyLeaves->count());
        foreach ($emptyLeaves->take(15) as $cat) {
            $this->line('  EMPTY LEAF #'.$cat->id.' ORANOS #'.($cat->oranos_category_id ?? 'none').' '.$cat->name);
        }
        if (!$this->option('live')) {
            $this->warn('Live provider comparison skipped. Add --live to fetch Oranos products safely.');
            return self::SUCCESS;
        }
        try {
            $feed = $oranos->getProducts();
        } catch (\Throwable $e) {
            $this->error('ORANOS LIVE READ FAILED: '.$e::class.' (inspect SSL, token and provider status)');
            return self::FAILURE;
        }
        $byProviderId = $categories->filter(fn ($c) => $c->oranos_category_id !== null)->keyBy(fn ($c) => (string) $c->oranos_category_id);
        $byName = collect();
        foreach ($categories as $cat) { $byName->put(trim($cat->name), $cat->id); }
        $localProviderIds = $products->pluck('oranos_product_id')->filter()->map(fn ($v) => (string) $v)->flip();
        $missingProducts = 0;
        $unmatchedCategories = 0;
        $unmappedExamples = [];
        foreach ($feed as $item) {
            $id = $item['id'] ?? null;
            if ($id !== null && !$localProviderIds->has((string) $id)) $missingProducts++;
            $parent = $item['parent_id'] ?? null;
            $name = trim((string) ($item['category_name'] ?? ''));
            if (!($parent && $byProviderId->has((string) $parent)) && !($name !== '' && $byName->has($name))) {
                $unmatchedCategories++;
                if (count($unmappedExamples) < 12) $unmappedExamples[] = '  ORANOS ITEM #'.($id ?? '?').' PARENT #'.($parent ?? '?').' CATEGORY '.$name;
            }
        }
        $this->line('LIVE ORANOS PRODUCT FEED: '.count($feed));
        $this->line('ORANOS FEED ITEMS NOT FOUND LOCALLY: '.$missingProducts);
        $this->line('ORANOS FEED ITEMS WITH NO LOCAL CATEGORY MATCH: '.$unmatchedCategories);
        foreach ($unmappedExamples as $example) $this->line($example);
        $this->warn('Counts are diagnostic only; no sync or data deletion was performed.');
        return self::SUCCESS;
    }
}
