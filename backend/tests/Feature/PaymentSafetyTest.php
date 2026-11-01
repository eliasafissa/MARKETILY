<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Api\Admin\AdminTransactionController;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_order(): void
    {
        $this->postJson('/api/orders', ['items' => [], 'payment_method' => 'cash_wallet'])
            ->assertUnauthorized();
    }

    public function test_deposit_request_never_credits_balance(): void
    {
        config()->set('services.manual_payments.sham_cash', 'TEST-RECEIVER');
        \App\Models\Setting::set('payment_syp_per_usd', '15000', \App\Models\Setting::GROUP_PAYMENT);
        $user = User::factory()->create(['balance' => 40]);
        Sanctum::actingAs($user);

        $this->postJson('/api/deposits', [
            'method' => 'sham_cash', 'amount' => 10, 'payment_reference' => 'TEST-RECEIPT-1', 'sender_account' => 'sender-12345',
        ])->assertCreated()->assertJsonPath('transaction.status', 'pending');

        $this->assertEquals(40, $user->fresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_direct_unverified_crypto_checkout_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $product = Product::factory()->create(['type' => CategoryType::Manual, 'price' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'payload' => ['player_id' => '123']]],
            'payment_method' => 'usdt',
            'meta' => ['wallet_address' => 'demo'],
        ])->assertUnprocessable();
        $this->assertEquals(100, $user->fresh()->balance);
    }

    public function test_same_checkout_key_does_not_charge_twice(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $product = Product::factory()->create(['type' => CategoryType::Manual, 'price' => 20]);
        Sanctum::actingAs($user);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'payload' => ['link' => 'test']]],
            'payment_method' => 'cash_wallet',
        ];
        $key = 'checkout-idem-1234567890';
        $one = $this->withHeader('Idempotency-Key', $key)->postJson('/api/orders', $payload)->assertCreated();
        $two = $this->withHeader('Idempotency-Key', $key)->postJson('/api/orders', $payload)->assertCreated();
        $this->assertEquals($one->json('order.id'), $two->json('order.id'));
        $this->assertEquals(80, $user->fresh()->balance);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_duplicate_transfer_receipt_is_rejected_even_for_another_account(): void
    {
        config()->set('services.manual_payments.binance_pay', 'merchant-id');
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Sanctum::actingAs($userA);
        $payload = [
            'method' => 'binance_pay', 'amount' => 12,
            'payment_reference' => 'PROVIDER-RECEIPT-001', 'sender_account' => 'sender-1',
        ];
        $a = $this->postJson('/api/deposits', $payload)->assertCreated()->json('transaction.id');
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->post('/api/deposits/'.$a.'/confirm', [
            'payment_reference' => 'PROVIDER-RECEIPT-001',
            'sender_account' => 'sender-1',
            'receipt' => \Illuminate\Http\UploadedFile::fake()->image('receipt.png'),
        ])->assertOk();
        $this->getJson('/api/deposits')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs($userB);
        $b = $this->postJson('/api/deposits', $payload)->assertCreated()->json('transaction.id');
        $this->post('/api/deposits/'.$b.'/confirm', [
            'payment_reference' => 'PROVIDER-RECEIPT-001',
            'sender_account' => 'sender-2',
            'receipt' => \Illuminate\Http\UploadedFile::fake()->image('receipt2.png'),
        ])->assertStatus(409);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_admin_must_match_provider_reference_before_approving(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $buyer = User::factory()->create(['balance' => 0]);
        $txn = Transaction::create([
            'user_id' => $buyer->id, 'type' => TransactionType::Deposit,
            'status' => TransactionStatus::Pending, 'amount' => 25, 'fee' => 0,
            'method' => 'sham_cash', 'payment_reference' => 'abc-transfer-999',
            'meta' => ['sender_account' => 'sender-123', 'stage' => 'awaiting_admin'],
        ]);
        Sanctum::actingAs($admin);
        $url = '/api/admin/deposits/' . $txn->id . '/approve';
        $proof = [
            'confirmed_reference' => 'wrong-reference',
            'verification_note' => 'Checked merchant statement',
            'sender_confirmed' => true, 'amount_confirmed' => true,
        ];
        $this->postJson($url, $proof)->assertUnprocessable();
        $this->assertEquals(0, $buyer->fresh()->balance);
        $proof['confirmed_reference'] = 'abc-transfer-999';
        $this->postJson($url, $proof)->assertOk();
        $this->postJson($url, $proof)->assertStatus(409);
        $this->assertEquals(25, $buyer->fresh()->balance);
    }

    public function test_reset_token_changes_password_once(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1']);
        $token = Password::broker()->createToken($user);
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'new-password-12345', 'password_confirmation' => 'new-password-12345',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-password-12345', $user->fresh()->password));
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'another-password-1', 'password_confirmation' => 'another-password-1',
        ])->assertUnprocessable();
    }
}
