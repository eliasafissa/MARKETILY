<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Setting;
use App\Services\OranosPrecisePriceSource;
use App\Services\PreciseMoney;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Independent price refresh; V4 commands and scheduler are never modified. */
class RefreshOranosPrecisePrices extends Command
{
    protected $signature = 'oranos:refresh-precise-prices {--apply : Commit validated provider price snapshot}';
    protected $description = 'Read Oranos exact costs, preview differences (or --apply after backup)';

    public function handle(OranosPrecisePriceSource $source): int
    {
        try {
            $api = $source->all(); // one read-only feed, fail atomically on invalid payload
            $products = Product::query()->whereNotNull('oranos_product_id')
                ->where('is_automation', true)->get(['id','oranos_product_id','base_price','pricing_mode']);
            if ($products->isEmpty()) throw new RuntimeException('No imported Oranos products.');
            $changes = []; $missing = 0;
            foreach ($products as $product) {
                $id = (int)$product->oranos_product_id;
                if (!isset($api[$id])) { $missing++; continue; }
                $cost = $api[$id];
                if (PreciseMoney::compare((string)$product->base_price, $cost) !== 0) $changes[] = $product->id;
            }
            $this->line('Feed items: '.count($api).', mapped: '.($products->count()-$missing).', missing: '.$missing.', different base_price: '.count($changes));
            if ($missing > 0 && $missing * 10 > $products->count()) {
                throw new RuntimeException('More than 10% of provider IDs have no valid price: incomplete feed; no writes.');
            }
            if ($missing > 0) $this->warn('Missing/invalid costs not updated; catalog visibility unchanged.');
            if (!$this->option('apply')) {
                $this->warn('Preview only. Existing data unchanged; run --apply only after DB backup and validation.');
                return self::SUCCESS;
            }
            $stamp = now();
            $markup = (string)(Setting::query()->where('key','oranos_markup_percent')->value('value') ?? '20');
            PreciseMoney::providerWithMarkup('1', $markup); // validate before opening write transaction
            DB::transaction(function () use ($products, $api, $stamp, $markup) {
                foreach ($products->chunk(500) as $chunk) {
                    $rows = [];
                    foreach ($chunk as $product) {
                        if (!isset($api[(int)$product->oranos_product_id])) continue;
                        $rows[] = [
                            'product_id' => $product->id,
                            'oranos_product_id' => (int)$product->oranos_product_id,
                            'unit_cost' => $api[(int)$product->oranos_product_id],
                            'fetched_at' => $stamp,
                            'created_at' => $stamp,
                            'updated_at' => $stamp,
                        ];
                        $update = ['base_price' => $api[(int)$product->oranos_product_id]];
                        if (($product->pricing_mode ?? 'auto') === 'auto') {
                            $update['price'] = PreciseMoney::providerWithMarkup($update['base_price'], $markup);
                        }
                        Product::query()->whereKey($product->id)->update($update);
                    }
                    if ($rows) DB::table('oranos_precise_prices')->upsert($rows, ['product_id'], ['oranos_product_id','unit_cost','fetched_at','updated_at']);
                }
            });
            // The base_price mirror may be overwritten by V4; the snapshot remains authoritative.
            $this->info('Exact snapshot applied to '.($products->count()-$missing).' products; unverified: '.$missing.'.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Provider price refresh aborted: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
