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
        // Preserve manually configured prices and activation flags.
        $percent = (string) Setting::get('oranos_markup_percent', '20');
        $updated = 0;
        $skipped = 0;

        Product::whereNotNull('oranos_product_id')
            ->where('pricing_mode', 'auto')
            ->chunkById(200, function ($products) use ($percent, &$updated, &$skipped) {
                foreach ($products as $product) {
                    $snapshot = \Illuminate\Support\Facades\DB::table('oranos_precise_prices')
                        ->where('product_id', $product->id)
                        ->where('oranos_product_id', $product->oranos_product_id)
                        ->value('unit_cost');
                    // Never silently manufacture a price from an unverified legacy cost.
                    if ($snapshot === null) { $skipped++; continue; }
                    try {
                        $price = \App\Services\PreciseMoney::providerWithMarkup((string) $snapshot, $percent);
                    } catch (\DomainException $e) { $skipped++; continue; }
                    if (\App\Services\PreciseMoney::compare((string) $product->getRawOriginal('price'), $price) !== 0) {
                        $product->update(['price' => $price]);
                        $updated++;
                    }
                }
            });

        $this->info("Updated {$updated} automatic prices; skipped {$skipped} without verified cost. No product visibility changed.");
        return self::SUCCESS;
    }
}
