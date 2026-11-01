<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepositFlowV4Test extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_deposit(): void
    {
        $this->postJson('/api/deposits', ['method' => 'sham_cash', 'amount' => 10])->assertUnauthorized();
    }

    public function test_admin_sets_exchange_rate_and_customer_stages_syp_quote(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        Setting::set('payment_syp_per_usd', '15000', Setting::GROUP_PAYMENT);
        Sanctum::actingAs($user);
        $r = $this->postJson('/api/deposits', ['method' => 'sham_cash', 'amount' => 150000, 'currency' => 'SYP']);
        $r->assertCreated()->assertJsonPath('transaction.amount', '10.00')
            ->assertJsonPath('deposit.transfer_currency', 'SYP');
        $this->assertEquals(0, $user->fresh()->balance);
    }

    public function test_manual_proof_waits_for_operator_and_duplicate_receipt_is_rejected(): void
    {
        Storage::fake('local');
        Setting::set('payment_syp_per_usd', '15000', Setting::GROUP_PAYMENT);
        $userA = User::factory()->create(['balance' => 0]);
        $userB = User::factory()->create(['balance' => 0]);
        Sanctum::actingAs($userA);
        $a = $this->postJson('/api/deposits', ['method' => 'syriatel_cash', 'amount' => 10])->assertCreated()->json('transaction.id');
        $this->post('/api/deposits/'.$a.'/confirm', [
            'payment_reference' => 'transfer-556677', 'sender_account' => 'mobile-a',
            'sender_name' => 'Sender A', 'receipt' => UploadedFile::fake()->image('receipt.png'),
        ])->assertOk();
        $this->assertEquals(0, $userA->fresh()->balance);
        Sanctum::actingAs($userB);
        $b = $this->postJson('/api/deposits', ['method' => 'syriatel_cash', 'amount' => 10])->assertCreated()->json('transaction.id');
        $this->post('/api/deposits/'.$b.'/confirm', [
            'payment_reference' => 'transfer-556677', 'sender_account' => 'mobile-b',
            'sender_name' => 'Sender B', 'receipt' => UploadedFile::fake()->image('receipt.png'),
        ])->assertStatus(409);
        $this->assertEquals(0, $userB->fresh()->balance);
    }

    public function test_binance_and_usdt_are_manual_even_if_auto_verifier_was_configured(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['balance' => 0]);
        Sanctum::actingAs($user);
        config()->set('services.manual_payments.usdt', '0x5be7d89e6e0d14f7007a2c516bf53f4b2fa278e3');
        config()->set('services.manual_payments.binance_pay', '1121616178');
        config()->set('services.binance_pay.auto_verify', true);

        foreach (['binance_pay', 'usdt'] as $method) {
            $id = $this->postJson('/api/deposits', ['method' => $method, 'amount' => 10])
                ->assertCreated()->json('transaction.id');
            $reference = $method === 'usdt' ? '0x'.str_repeat('a', 64) : 'binance-manual-receipt-3333';
            $this->post('/api/deposits/'.$id.'/confirm', [
                'payment_reference' => $reference,
                'sender_account' => 'platform-account-333',
                'receipt' => UploadedFile::fake()->image('receipt.png'),
            ])->assertOk()->assertJsonPath('verification', 'pending_admin_review');
            $this->assertEquals(0, $user->fresh()->balance);
            $this->post('/api/deposits/'.$id.'/confirm', [
                'payment_reference' => $reference,
                'sender_account' => 'platform-account-333',
                'receipt' => UploadedFile::fake()->image('receipt2.png'),
            ])->assertStatus(409);
        }
    }

    public function test_sham_manual_deposit_requires_receipt_and_admin_review(): void
    {
        Storage::fake('local');
        Setting::set('payment_syp_per_usd', '15000', Setting::GROUP_PAYMENT);
        $user = User::factory()->create(['balance' => 0, 'name' => 'Ahmad Mohamed Hamza']);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/deposits', ['method' => 'sham_cash', 'amount' => 10])
            ->assertCreated()->json('transaction.id');
        $payload = ['payment_reference' => 'ref-11002334', 'sender_account' => 'sender-1102', 'sender_name' => 'Ahmad Mohamed Hamza'];
        $this->post('/api/deposits/'.$id.'/confirm', $payload)->assertUnprocessable();
        $this->post('/api/deposits/'.$id.'/confirm', $payload + ['receipt' => UploadedFile::fake()->image('proof.jpg')])
            ->assertOk()->assertJsonPath('verification', 'pending_admin_review');
        $this->assertEquals(0, $user->fresh()->balance);
    }
}
