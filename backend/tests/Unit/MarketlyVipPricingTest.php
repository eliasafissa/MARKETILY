<?php

namespace Tests\Unit;

use App\Enums\VipLevel;
use App\Services\CatalogPricing;
use App\Services\VipService;
use PHPUnit\Framework\TestCase;

class MarketlyVipPricingTest extends TestCase
{
    public function test_automatic_thresholds_and_vip4_absent(): void
    {
        $vip = new VipService();
        $this->assertSame(VipLevel::None, $vip->earnedLevel(999.99));
        $this->assertSame(VipLevel::Vip1, $vip->earnedLevel(1000));
        $this->assertSame(VipLevel::Vip1, $vip->earnedLevel(1999.99));
        $this->assertSame(VipLevel::Vip2, $vip->earnedLevel(2000));
        $this->assertSame(VipLevel::Vip3, $vip->earnedLevel(5000));
        $this->assertSame(VipLevel::Vip3, $vip->earnedLevel(100000));
    }

    public function test_vip_discount_never_below_provider_cost(): void
    {
        $this->assertSame(50.0, CatalogPricing::calculate(60, 50, 25, true));
        $this->assertSame(54.0, CatalogPricing::calculate(60, 50, 10, true));
        $this->assertSame(90.0, CatalogPricing::calculate(100, 50, 10, true));
        $this->assertSame(0.01, CatalogPricing::calculate(0.02, 0.004, 100, true));
    }

    public function test_manual_product_discount_and_invalid_cost(): void
    {
        $this->assertSame(45.0, CatalogPricing::calculate(50, 0, 10, false));
        $this->assertSame(0.01, CatalogPricing::calculate(3, 0, 100, false));
        $this->expectException(\DomainException::class);
        CatalogPricing::calculate(4, 5, 0, true);
    }
}
