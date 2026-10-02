<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_auto_order_with_sufficient_balance(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $product = Product::factory()->create([
            'type' => CategoryType::Auto,
            'price' => 100,
            'stock' => 10,
        ]);

        $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash_wallet',
        ])->assertCreated();

        $this->assertEquals(900, $user->fresh()->balance);
        $this->assertEquals(OrderStatus::Completed, $user->orders()->first()->status);
    }

    public function test_user_cannot_create_order_with_insufficient_balance(): void
    {
        $user = User::factory()->create(['balance' => 50]);
        $product = Product::factory()->create(['type' => CategoryType::Auto, 'price' => 100, 'stock' => 10]);

        $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash_wallet',
        ])->assertStatus(422);
    }

    public function test_manual_order_is_pending(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $product = Product::factory()->create([
            'type' => CategoryType::Manual,
            'price' => 50,
            'stock' => 100,
        ]);

        $this->actingAs($user)->postJson('/api/orders', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'payload' => ['platform' => 'telegram', 'link' => 'https://t.me/test'],
            ]],
            'payment_method' => 'cash_wallet',
        ])->assertCreated()->assertJsonPath('order.status', OrderStatus::Pending->value);

        // Balance should NOT be debited for manual orders
        $this->assertEquals(1000, $user->fresh()->balance);
    }

    public function test_admin_can_complete_manual_order(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create(['balance' => 1000]);
        $product = Product::factory()->create(['type' => CategoryType::Manual, 'price' => 50, 'stock' => 100]);

        $orderResponse = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'payload' => ['platform' => 'telegram'],
            ]],
            'payment_method' => 'cash_wallet',
        ])->assertCreated();

        $orderId = $orderResponse->json('order.id');

        $this->actingAs($admin)->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertOk()->assertJsonPath('order.status', OrderStatus::Completed->value);
    }

    public function test_moderator_can_process_manual_order(): void
    {
        $mod = User::factory()->create();
        $mod->assignRole('moderator');
        $user = User::factory()->create(['balance' => 1000]);
        $product = Product::factory()->create(['type' => CategoryType::Manual, 'price' => 50, 'stock' => 100]);

        $orderResponse = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'payload' => ['platform' => 'telegram'],
            ]],
            'payment_method' => 'cash_wallet',
        ])->assertCreated();

        $orderId = $orderResponse->json('order.id');

        $this->actingAs($mod)->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'processing',
        ])->assertOk();
    }

    public function test_regular_user_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->getJson('/api/admin/users')->assertStatus(403);
    }
}
