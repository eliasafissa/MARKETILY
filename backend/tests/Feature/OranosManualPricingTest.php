<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Setting;
use App\Console\Commands\SyncOranosProducts;
use App\Services\OranosMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OranosManualPricingTest extends TestCase
{
    use RefreshDatabase;

    private function providerProduct(): Product
    {
        return Product::factory()->create([
            'oranos_product_id' => 123456,
            'base_price' => 5,
            'price' => 6,
            'pricing_mode' => 'auto',
            'is_automation' => true,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_set_fifty_dollars_on_a_five_dollar_provider_product(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = $this->providerProduct();
        $this->actingAs($admin)->patchJson('/api/admin/products/'.$product->id, [
            'price' => 50,
            'pricing_mode' => 'manual',
        ])->assertOk();
        $this->assertEquals(50, (float) $product->fresh()->price);
        $this->assertEquals('manual', $product->fresh()->pricing_mode);
    }

    public function test_price_below_provider_cost_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = $this->providerProduct();
        $this->actingAs($admin)->patchJson('/api/admin/products/'.$product->id, [
            'price' => 4.9999,
            'pricing_mode' => 'manual',
        ])->assertStatus(422)->assertJsonValidationErrors('price');
        $this->assertEquals(6, (float) $product->fresh()->price);
    }

    public function test_manual_price_survives_provider_resync_and_cost_rise_disables_product(): void
    {
        $product = $this->providerProduct();
        $product->update(['price' => 50, 'pricing_mode' => 'manual']);
        $category = $product->category;
        $category->update(['oranos_category_id' => 77]);

        $provider = $this->mock(OranosMarketService::class);
        $provider->shouldReceive('getProducts')->once()->andReturn([[
            'id' => 123456,
            'name' => $product->name,
            'price' => 55,
            'available' => true,
            'parent_id' => 77,
            'params' => ['account'],
            'qty_values' => null,
        ]]);

        $result = \Illuminate\Support\Facades\Artisan::call('oranos:sync-products');
        $this->assertEquals(0, $result);
        $this->assertEquals(50, (float) $product->fresh()->price);
        $this->assertEquals(55, (float) $product->fresh()->base_price);
        $this->assertFalse((bool) $product->fresh()->is_active);
    }

    public function test_apply_markup_does_not_overwrite_manual_price(): void
    {
        $product = $this->providerProduct();
        $product->update(['price' => 50, 'pricing_mode' => 'manual']);
        Setting::set('oranos_markup_percent', '40');
        $this->artisan('oranos:apply-markup')->assertExitCode(0);
        $this->assertEquals(50, (float) $product->fresh()->price);
    }
}
