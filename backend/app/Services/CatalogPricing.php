<?php

namespace App\Services;

use App\Enums\VipLevel;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class CatalogPricing
{
    public static function percentageFor(VipLevel $level): float
    {
        if ($level === VipLevel::None) return 0.0;
        $raw = (float) Setting::get($level->value . '_discount_percent', 0);
        return min(100.0, max(0.0, $raw));
    }

    /** All values remain USD. Provider products always have a cost floor. */
    public static function calculate(float $listPrice, float $providerCost, float $percent, bool $provider): float
    {
        if (! is_finite($listPrice) || $listPrice <= 0 || ! is_finite($percent)) {
            throw new \DomainException('Invalid product price or VIP discount.');
        }
        if ($provider && (! is_finite($providerCost) || $providerCost <= 0 || $listPrice + 0.00001 < $providerCost)) {
            throw new \DomainException('Provider cost is invalid or exceeds the selling price.');
        }
        $percent = max(0.0, min(100.0, $percent));
        $discounted = round($listPrice * (1 - $percent / 100), 4);
        return $provider ? max(0, $providerCost, $discounted) : max(0.01, $discounted);
    }

    /** Cost from the independent Oranos snapshot, not legacy rounded base_price. */
    private static function currentCost(Product $product): string
    {
        if ($product->oranos_product_id === null) return '0';
        $snapshot = DB::table('oranos_precise_prices')
            ->where('product_id', $product->id)
            ->where('oranos_product_id', $product->oranos_product_id)
            ->value('unit_cost');
        // Legacy fallback keeps products visible, but is NOT a verified live supplier quote.
        return PreciseMoney::decimal((string) ($snapshot ?? $product->getRawOriginal('base_price')));
    }

    /** Full precision quote; retain decimals internally. */
    public static function exactForProduct(Product $product, VipLevel $level, ?string $storePrice = null): string
    {
        $provider = $product->oranos_product_id !== null;
        $base = $provider
            ? self::currentCost($product)
            : PreciseMoney::decimal('0');
        $regular = PreciseMoney::decimal((string) $product->getRawOriginal('price'));

        // Use provider cost for automatically priced products.
        // Never add markup twice to manually overridden prices.
        if ($provider && ($product->pricing_mode ?? 'auto') === 'auto' && $storePrice === null) {
            $percent = PreciseMoney::decimal((string) Setting::get('oranos_markup_percent', 20));
            $regular = PreciseMoney::providerWithMarkup($base, $percent);
        } elseif ($storePrice !== null) {
            $regular = PreciseMoney::decimal($storePrice);
        }

        if (PreciseMoney::compare($regular, '0') <= 0) {
            throw new \DomainException('Invalid product price.');
        }

        $vip = PreciseMoney::decimal((string) Setting::get($level->value . '_discount_percent', 0));

        if ($provider) {
            if (PreciseMoney::compare($base, '0') <= 0 || PreciseMoney::compare($regular, $base) < 0) {
                throw new \DomainException('Provider cost exceeds selling price.');
            }

            return PreciseMoney::providerVipPrice($regular, $base, $vip);
        }

        $discounted = PreciseMoney::sub(
            $regular,
            PreciseMoney::percentOf($regular, $vip)
        );

        return PreciseMoney::compare($discounted, '0') > 0
            ? $discounted
            : PreciseMoney::decimal('0.01');
    }
    public static function forProduct(Product $product, VipLevel $level, ?float $listPrice = null): float
    {
        $isProvider = $product->oranos_product_id !== null;
        return self::calculate(
            $listPrice ?? (float) $product->price,
            (float) $product->base_price,
            self::percentageFor($level),
            $isProvider,
        );
    }

    public static function display(Product $product, VipLevel $level): Product
    {
        $regular = PreciseMoney::decimal((string) $product->getRawOriginal('price'));
        if (
            $product->oranos_product_id !== null
            && ($product->pricing_mode ?? 'auto') === 'auto'
        ) {
            $regular = PreciseMoney::providerWithMarkup(
                self::currentCost($product),
                (string) Setting::get('oranos_markup_percent', 20)
            );
        }
        $effective = self::exactForProduct($product, $level);
        $product->setAttribute('regular_price', $regular);
        $product->setAttribute('price', $effective);
        $product->setAttribute('vip_discount_percent', self::percentageFor($level));
        return $product;
    }
}
