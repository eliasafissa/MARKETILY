<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FreshSyncOranos extends Command
{
    protected $signature = 'oranos:fresh-sync {--force : Skip confirmation}';
    protected $description = 'Wipe data and do a full clean recursive sync from Oranos';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will DELETE all products, categories, orders, stores. Continue?', false)) {
                return 0;
            }
        }

        $this->info('Cleaning up database...');
        DB::transaction(function () {
            DB::table('favorites')->delete();
            DB::table('store_product')->delete();
            OrderItem::query()->delete();
            Order::query()->delete();
            Store::query()->delete();
            Product::query()->delete();
            Category::query()->delete();
        });
        $this->info('Database cleaned.');
        $this->newLine();

        $this->info('=== Step 1/2: Syncing FULL category tree (recursive) ===');
        $start = microtime(true);
        $this->call('oranos:sync-categories');
        $this->line('Took: ' . round(microtime(true) - $start, 1) . 's');
        $this->newLine();

        $this->info('=== Step 2/2: Syncing products ===');
        $start = microtime(true);
        $this->call('oranos:sync-products');
        $this->line('Took: ' . round(microtime(true) - $start, 1) . 's');
        $this->newLine();

        $this->info('=== Summary ===');
        $this->line('Categories: ' . Category::count());
        $this->line('Root only:  ' . Category::whereNull('parent_id')->count());
        $this->line('Products:   ' . Product::count());
        $this->line('Active:     ' . Product::where('is_active', true)->count());

        return 0;
    }
}
