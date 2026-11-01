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
        config(['services.oranos.token' => 'test-token']);
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
            ['product_id' => $product->id, 'quantity' => 2, 'payload' => ['account' => 'sample']],
        ], 'cash_wallet');

        $this->assertEquals(OrderStatus::Pending, $order->status);
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

    public function test_create_manual_order_is_pending_and_reserves_wallet_balance(): void
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
        $this->assertEquals(400, $user->fresh()->balance);
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

    public function test_manual_order_reject_refunds_only_once(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $product = Product::factory()->create([
            'type' => CategoryType::Manual, 'price' => 100, 'stock' => 10,
            'is_automation' => false, 'oranos_product_id' => null,
        ]);
        $order = $this->orderService->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1, 'payload' => ['account' => 'sample']],
        ], 'cash_wallet');
        $this->assertEquals(400, $user->fresh()->balance);
        $this->orderService->markRejected($order, 'test rejection');
        $this->orderService->markRejected($order->fresh(), 'test rejection');
        $this->assertEquals(500, $user->fresh()->balance);
        $this->assertEquals(1, Transaction::where('type', TransactionType::Refund)->count());
    }
}
