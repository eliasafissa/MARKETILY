<?php
namespace Tests\Unit;

use App\Services\OranosPrecisePriceSource;
use PHPUnit\Framework\TestCase;

class MarketllyPreciseSupplierFeedTest extends TestCase
{
    public function test_supplier_scientific_price_is_preserved_as_decimal_string(): void
    {
        $this->assertSame('0.000082245953850000010000', OranosPrecisePriceSource::normalizeRawPrice('8.224595385000001e-5'));
        $result = OranosPrecisePriceSource::decodeProductList('[{"id":1248,"price":8.224595385000001e-5},{"id":2304,"price":0.0001095668461875}]');
        $this->assertSame('0.000082245953850000010000', $result[1248]);
        $this->assertSame('0.000109566846187500000000', $result[2304]);
    }
}
