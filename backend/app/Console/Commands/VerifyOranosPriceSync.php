<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;

class VerifyOranosPriceSync extends Command
{
    protected $signature = 'oranos:verify-price-sync';
    protected $description = 'Verify Oranos product prices match our base_price';

    public function handle(OranosMarketService $service): int
    {
        $products = Product::whereNotNull('oranos_product_id')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            $this->warn('No Oranos-linked products found.');
            return 1;
        }

        $this->info('Fetching Oranos products...');
        $oranosProducts = $service->getProducts();
        $oranosMap = [];
        foreach ($oranosProducts as $op) {
            $oranosMap[(string)$op['id']] = $op;
        }

        $matches = 0;
        $mismatches = 0;

        foreach ($products as $product) {
            $oranosProduct = $oranosMap[(string)$product->oranos_product_id];
            if (!$oranosProduct) {
                $this->warn("[MISSING] {$product->slug} (oranos_id: {$product->oranos_product_id}) not found in Oranos");
                $mismatches++;
                continue;
            }

            $oranosPrice = (float)($oranosProduct['price'] ?? 0);
            $ourBasePrice = (float)$product->base_price;

            if (abs($oranosPrice - $ourBasePrice) < 0.01) {
                $this->info("[MATCH] {$product->slug}: Oranos={$oranosPrice} Ours={$ourBasePrice}");
                $matches++;
            } else {
                $this->error("[MISMATCH] {$product->slug}: Oranos={$oranosPrice} Ours={$ourBasePrice} Diff=" . abs($oranosPrice - $ourBasePrice));
                $mismatches++;
            }
        }

        $this->newLine();
        $this->info("Summary: {$matches} MATCH, {$mismatches} MISMATCH out of {$products->count()} checked.");

        return $mismatches > 0 ? 1 : 0;
    }
}
