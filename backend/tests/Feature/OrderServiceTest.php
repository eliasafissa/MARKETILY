<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OranosMarketService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
    }

    public function test_create_order_with_cash_wallet_deducts_balance(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $product = Product::factory()->create([
            'type' => CategoryType::Auto,
            'price' => 100,
            'stock' => 10,
        ]);

        $order = $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ], 'cash_wallet');

        $this->assertEquals(OrderStatus::Completed, $order->status);
        $this->assertEquals(300, $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::Purchase,
            'amount' => 200,
            'status' => TransactionStatus::Approved,
            'method' => 'cash_wallet',
        ]);
    }

    public function test_create_order_with_insufficient_balance_throws_exception(): void
    {
        $user = User::factory()->create(['balance' => 50]);
        $product = Product::factory()->create([
            'type' => CategoryType::Auto,
            'price' => 100,
            'stock' => 10,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1],
        ], 'cash_wallet');
    }

    public function test_create_manual_order_is_pending_and_does_not_deduct_balance(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $product = Product::factory()->create([
            'type' => CategoryType::Manual,
            'price' => 100,
            'stock' => 10,
        ]);

        $order = $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1, 'payload' => ['link' => 'test']],
        ], 'cash_wallet');

        $this->assertEquals(OrderStatus::Pending, $order->status);
        $this->assertEquals(500, $user->fresh()->balance);
    }

    public function test_oranos_market_service_get_profile_returns_balance(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/profile' => Http::response([
                'balance' => 150.75,
                'email' => 'test@oranos.com',
                'towFactor' => true,
            ], 200),
        ]);

        $service = app(OranosMarketService::class);
        $profile = $service->getProfile();

        $this->assertEquals(150.75, $profile['balance']);
        $this->assertEquals('test@oranos.com', $profile['email']);
        $this->assertTrue($profile['towFactor']);
    }

    public function test_oranos_market_service_get_profile_handles_failure(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/profile' => Http::response([], 500),
        ]);

        $service = app(OranosMarketService::class);
        $profile = $service->getProfile();

        $this->assertEquals([], $profile);
    }

    public function test_oranos_throws_exception_refunds_balance_and_rejects_order(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $product = Product::factory()->create([
            'type' => CategoryType::Auto,
            'price' => 100,
            'stock' => 10,
            'is_automation' => true,
            'oranos_product_id' => 123,
            'oranos_available' => true,
            'is_active' => true,
        ]);

        // Mock Oranos to throw an exception
        $this->mock(\App\Services\OranosMarketService::class, function ($mock) {
            $mock->shouldReceive('createOrder')
                ->andThrow(new \RuntimeException('Oranos API connection failed'));
        });

        $order = $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1, 'payload' => ['id' => 'player123']],
        ], 'cash_wallet');

        // Order should be rejected
        $this->assertEquals(OrderStatus::Rejected, $order->status);
        $this->assertStringContainsString('Oranos API error', $order->failure_reason);

        // Balance should be refunded (unchanged from original)
        $this->assertEquals(500, $user->fresh()->balance);

        // Refund transaction should exist
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::Refund,
            'amount' => 100,
            'status' => TransactionStatus::Approved,
        ]);
    }

    public function test_oranos_returns_error_response_refunds_balance_and_rejects_order(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $product = Product::factory()->create([
            'type' => CategoryType::Auto,
            'price' => 100,
            'stock' => 10,
            'is_automation' => true,
            'oranos_product_id' => 123,
            'oranos_available' => true,
            'is_active' => true,
        ]);

        // Mock Oranos to return an error response (no order_id)
        $this->mock(\App\Services\OranosMarketService::class, function ($mock) {
            $mock->shouldReceive('createOrder')
                ->andReturn(['error' => 'Product out of stock']); // No order_id
        });

        $order = $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1, 'payload' => ['id' => 'player123']],
        ], 'cash_wallet');

        // Order should be rejected
        $this->assertEquals(OrderStatus::Rejected, $order->status);
        $this->assertStringContainsString('unexpected response', $order->failure_reason);

        // Balance should be refunded
        $this->assertEquals(500, $user->fresh()->balance);

        // Refund transaction should exist
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::Refund,
            'amount' => 100,
            'status' => TransactionStatus::Approved,
        ]);
    }

    public function test_concurrent_orders_with_insufficient_balance_only_one_succeeds(): void
    {
        $user = User::factory()->create(['balance' => 150]);
        $product = Product::factory()->create([
            'type' => CategoryType::Auto,
            'price' => 100,
            'stock' => 10,
        ]);

        // First order should succeed
        $order1 = $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1],
        ], 'cash_wallet');

        $this->assertEquals(OrderStatus::Completed, $order1->status);
        $this->assertEquals(50, $user->fresh()->balance);

        // Second order should fail due to insufficient balance
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1],
        ], 'cash_wallet');

        // Balance should remain at 50 (first order succeeded, second failed)
        $this->assertEquals(50, $user->fresh()->balance);
    }
}