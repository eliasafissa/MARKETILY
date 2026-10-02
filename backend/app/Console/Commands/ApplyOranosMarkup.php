<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Console\Command;

class ApplyOranosMarkup extends Command
{
    protected $signature = 'oranos:apply-markup';
    protected $description = 'Recompute all Oranos product prices from base_price using current markup percentage';

    public function handle(): int
    {
        $markupPercent = (float) Setting::get('oranos_markup_percent', 20);
        $markup = 1 + ($markupPercent / 100);

        $products = Product::whereNotNull('oranos_product_id')->get();
        $updated = 0;

        foreach ($products as $product) {
            $basePrice = (float) $product->base_price;
            $newPrice = round($basePrice * $markup, 2);

            if ((float) $product->price !== $newPrice) {
                $product->update(['price' => $newPrice]);
                $updated++;
            }
        }

        // Also update store prices for automation products
        $storeMarkupPercent = (float) Setting::get('store_markup_percent', 10);
        $storeMarkup = 1 + ($storeMarkupPercent / 100);

        $updatedStores = 0;
        \DB::table('stores')->orderBy('id')->chunk(50, function ($stores) use (&$updatedStores, $storeMarkup) {
            foreach ($stores as $store) {
                $rows = \DB::table('store_product')
                    ->join('products', 'store_product.product_id', '=', 'products.id')
                    ->where('store_product.store_id', $store->id)
                    ->where('products.is_automation', true)
                    ->select('store_product.id as pivot_id', 'products.price as product_price')
                    ->get();

                foreach ($rows as $row) {
                    \DB::table('store_product')
                        ->where('id', $row->pivot_id)
                        ->update(['custom_price' => round((float) $row->product_price * $storeMarkup, 2)]);
                }
                $updatedStores++;
            }
        });

        $this->info("Recomputed prices for {$updated} products using {$markupPercent}% markup. Updated {$updatedStores} store prices using {$storeMarkupPercent}% markup.");

        return 0;
    }
}
