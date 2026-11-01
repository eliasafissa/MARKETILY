<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\DepositCurrency;
use App\Services\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketllyDepositAndMailCooldownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No test can issue real outbound HTTP, email or queued notifications.
        Http::preventStrayRequests();
        Mail::fake();
        Bus::fake();
    }

    public function test_separate_syriatel_exchange_rate_and_usd_sham_receiver(): void
    {
        Setting::set('payment_syp_per_usd', '15000', Setting::GROUP_PAYMENT);
        $currency = app(DepositCurrency::class);
        $this->assertNull($currency->rate('syriatel_cash'));
        Setting::set('payment_syriatel_syp_per_usd', '12000', Setting::GROUP_PAYMENT);
        Setting::set('payment_sham_cash_usd_receiver', 'SHAM-USD-REAL-ACCOUNT', Setting::GROUP_PAYMENT);
        config()->set('services.manual_payments.sham_cash', 'SHAM-SYP-REAL-ACCOUNT');
        config()->set('services.manual_payments.syriatel_cash', 'SYRIATEL-REAL-ACCOUNT');

        $this->assertSame(12000.0, $currency->rate('syriatel_cash'));
        $this->assertSame(15000.0, $currency->rate('sham_cash'));
        $this->assertSame(10.0, $currency->quote(120000, 'SYP', 'syriatel_cash')['amount_usd']);
        $this->assertSame(10.0, $currency->quote(150000, 'SYP', 'sham_cash')['amount_usd']);
        $this->assertSame(10.0, $currency->quote(10, 'USD', 'sham_cash_usd')['amount_usd']);

        $methods = collect(app(PaymentGatewayManager::class)->publicMethods())->keyBy('id');
        $this->assertTrue($methods['sham_cash_usd']['enabled']);
        $this->assertSame('USD', $methods['sham_cash_usd']['currency']);
        $this->assertSame(12000.0, $methods['syriatel_cash']['syp_per_usd']);
        $this->assertSame('SHAM-USD-REAL-ACCOUNT', $methods['sham_cash_usd']['receiver']);

        $user = User::factory()->create(['balance' => '0', 'email_verified_at' => now()]);
        Sanctum::actingAs($user);
        $this->postJson('/api/deposits/preview', ['method' => 'sham_cash_usd', 'amount' => 10, 'currency' => 'USD'])
            ->assertOk()->assertJsonPath('amount_usd', 10)->assertJsonPath('deposit.transfer_currency', 'USD');
        $this->postJson('/api/deposits/preview', ['method' => 'syriatel_cash', 'amount' => 120000, 'currency' => 'SYP'])
            ->assertOk()->assertJsonPath('amount_usd', 10)->assertJsonPath('deposit.rate_snapshot', 12000);
        $this->assertDatabaseCount('transactions', 0);

        Storage::fake('local');
        $this->post('/api/deposits', [
            'method' => 'sham_cash_usd', 'amount' => '10', 'currency' => 'USD',
            'payment_reference' => 'SHAM-USD-TEST-0001',
            'receipt' => UploadedFile::fake()->image('sham-usd-proof.png'),
            'receiver_confirmed' => '1',
            'expected_receiver' => 'SHAM-USD-REAL-ACCOUNT',
            'expected_transfer_amount' => '10', 'expected_transfer_currency' => 'USD',
        ])->assertCreated()->assertJsonPath('verification', 'pending_admin_review');
        $this->assertEquals(0, $user->fresh()->balance);
    }

    public function test_password_reset_link_cooldown_applies_even_for_unknown_email(): void
    {
        Cache::flush();
        Mail::fake();
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'localhost');
        config()->set('mail.from.address', 'noreply@example.test');
        $payload = ['email' => 'missing-address@example.test'];
        $first = $this->postJson('/api/auth/forgot-password', $payload);
        $first->assertOk()->assertJsonPath('retry_after', 30);
        $this->postJson('/api/auth/forgot-password', $payload)->assertStatus(429);
    }

    public function test_email_confirmation_second_send_is_blocked_for_thirty_seconds(): void
    {
        Cache::flush();
        Mail::fake();
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'localhost');
        config()->set('mail.from.address', 'noreply@example.test');
        $user = User::factory()->create(['email_verified_at' => null]);
        Sanctum::actingAs($user);
        $this->postJson('/api/auth/email-verification/send')->assertOk()->assertJsonPath('retry_after', 30);
        $this->postJson('/api/auth/email-verification/send')->assertStatus(429);
    }
}
