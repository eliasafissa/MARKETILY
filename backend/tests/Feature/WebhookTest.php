<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_binance_pay_webhook_with_valid_signature_approves_deposit(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $tx = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Deposit,
            'amount' => 100,
            'status' => TransactionStatus::Pending,
            'method' => 'binance_pay',
            'gateway_ref' => 'binance_test_ref_1',
        ]);

        $payload = json_encode([
            'reference' => 'binance_test_ref_1',
            'status' => 'PAID',
            'amount' => 100,
        ]);
        $secret = config('services.binance_pay.secret');
        $signature = hash_hmac('sha512', $payload, $secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/payments/binance_pay',
            [],
            [],
            [],
            ['HTTP_X-BINANCE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk();
        $this->assertEquals(TransactionStatus::Approved, $tx->fresh()->status);
        $this->assertEquals(100, $user->fresh()->balance);
    }

    public function test_binance_pay_webhook_with_invalid_signature_rejected(): void
    {
        $response = $this->call(
            'POST',
            '/api/webhooks/payments/binance_pay',
            [],
            [],
            [],
            ['HTTP_X-BINANCE-SIGNATURE' => 'invalid', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['reference' => 'x', 'status' => 'PAID', 'amount' => 100])
        );
        $response->assertStatus(422);
    }

    public function test_usdt_webhook_with_valid_signature_approves_deposit(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $tx = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Deposit,
            'amount' => 50,
            'status' => TransactionStatus::Pending,
            'method' => 'usdt',
            'gateway_ref' => 'usdt_test_ref_1',
        ]);

        $payload = json_encode([
            'reference' => 'usdt_test_ref_1',
            'status' => 'confirmed',
            'expected_amount' => 50,
            'amount' => 50,
        ]);
        $secret = config('services.usdt.secret');
        $signature = hash_hmac('sha256', $payload, $secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/payments/usdt',
            [],
            [],
            [],
            ['HTTP_X-USDT-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk();
        $this->assertEquals(TransactionStatus::Approved, $tx->fresh()->status);
    }

    public function test_usdt_webhook_rejects_amount_mismatch(): void
    {
        $payload = json_encode([
            'reference' => 'usdt_test_ref_2',
            'status' => 'confirmed',
            'expected_amount' => 50,
            'amount' => 100,
        ]);
        $secret = config('services.usdt.secret');
        $signature = hash_hmac('sha256', $payload, $secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/payments/usdt',
            [],
            [],
            [],
            ['HTTP_X-USDT-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $response->assertStatus(422);
    }
}
