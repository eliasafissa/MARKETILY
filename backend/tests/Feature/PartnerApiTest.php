<?php

namespace Tests\Feature;

use App\Models\PartnerApiRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\OranosMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    private function getAuthenticatedUserWithApprovedRequest(): User
    {
        $user = User::factory()->create(['balance' => 1000, 'api_key' => 'test-key-123']);
        PartnerApiRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);
        return $user;
    }

    public function test_partner_categories_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/partner/categories');
        $response->assertStatus(401);
        $response->assertJson(['error' => 'invalid_token']);
    }

    public function test_partner_categories_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeaders(['api-token' => 'invalid'])->getJson('/api/partner/categories');
        $response->assertStatus(401);
    }

    public function test_partner_categories_without_approved_request_returns_403(): void
    {
        $user = User::factory()->create(['api_key' => 'test-key']);
        PartnerApiRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders(['api-token' => 'test-key'])->getJson('/api/partner/categories');
        $response->assertStatus(403);
        $response->assertJson(['error' => 'no_approved_request']);
    }

    public function test_partner_categories_with_approved_request_returns_200(): void
    {
        $user = $this->getAuthenticatedUserWithApprovedRequest();

        $response = $this->withHeaders(['api-token' => 'test-key-123'])->getJson('/api/partner/categories');

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'data']);
    }

    public function test_partner_products_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/partner/products');
        $response->assertStatus(401);
    }

    public function test_partner_create_order_insufficient_balance_returns_402(): void
    {
        $user = User::factory()->create(['balance' => 10, 'api_key' => 'test-key']);
        PartnerApiRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);

        // Mock Oranos service to avoid real API calls
        $this->mock(OranosMarketService::class, function ($mock) {
            $mock->shouldReceive('createOrder')->andReturn(['order_id' => 'oranos-123']);
            $mock->shouldReceive('checkOrders')->andReturn(['data' => [['status' => 'completed']]]);
        });

        $product = Product::factory()->create([
            'price' => 100,
            'stock' => 10,
            'is_automation' => true,
            'is_active' => true,
            'oranos_product_id' => 123,
            'oranos_available' => true,
        ]);

        $response = $this->withHeaders(['api-token' => 'test-key'])->postJson('/api/partner/orders', [
            'product_slug' => $product->slug,
            'quantity' => 1,
            'params' => ['id' => 'player123'],
        ]);

        $response->assertStatus(402);
        $response->assertJson(['ok' => false, 'error' => 'insufficient_balance']);
    }

    public function test_partner_create_order_sufficient_balance_creates_order(): void
    {
        $user = User::factory()->create(['balance' => 1000, 'api_key' => 'test-key']);
        PartnerApiRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);

        // Mock Oranos service to avoid real API calls
        $this->mock(OranosMarketService::class, function ($mock) {
            $mock->shouldReceive('createOrder')->andReturn(['order_id' => 'oranos-123']);
            $mock->shouldReceive('checkOrders')->andReturn(['data' => [['status' => 'completed']]]);
        });

        $product = Product::factory()->create([
            'price' => 100,
            'stock' => 10,
            'is_automation' => true,
            'is_active' => true,
            'oranos_product_id' => 123,
            'oranos_available' => true,
        ]);

        $response = $this->withHeaders(['api-token' => 'test-key'])->postJson('/api/partner/orders', [
            'product_slug' => $product->slug,
            'quantity' => 1,
            'params' => ['id' => 'player123'],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'payment_method' => 'partner_api']);
        $this->assertEquals(900, $user->fresh()->balance);
    }
}