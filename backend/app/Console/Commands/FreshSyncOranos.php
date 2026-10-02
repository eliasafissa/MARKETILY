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
    protected $description = 'Wipe demo products/categories/orders/stores and do a full clean sync from Oranos';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('This will DELETE:');
            $this->warn('  - All products');
            $this->warn('  - All categories');
            $this->warn('  - All orders + order_items');
            $this->warn('  - All stores + store_product pivots');
            $this->warn('Will KEEP: users, settings, transactions, partner_api_requests');
            $this->newLine();
            if (! $this->confirm('Continue?', false)) {
                $this->info('Aborted.');
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

        $this->info('=== Step 1/2: Syncing categories from Oranos ===');
        $this->call('oranos:sync-categories');
        $this->newLine();

        $this->info('=== Step 2/2: Syncing products from Oranos ===');
        $this->call('oranos:sync-products');
        $this->newLine();

        $this->info('=== Summary ===');
        $this->line('Categories: ' . Category::count());
        $this->line('Products:   ' . Product::count());
        $this->line('Active:     ' . Product::where('is_active', true)->count());

        return 0;
    }
}
