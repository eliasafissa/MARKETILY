<?php

namespace Tests\Feature;

use App\Enums\VipLevel;
use App\Models\Setting;
use App\Models\User;
use App\Services\VipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipServiceTest extends TestCase
{
    use RefreshDatabase;

    private VipService $vip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vip = app(VipService::class);
    }

    public function test_regular_user_has_zero_withdrawal_limit(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::None]);
        $this->assertEquals(0.0, $this->vip->withdrawalLimit($user));
    }

    public function test_vip1_withdrawal_limit_defaults_to_1000(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip1]);
        $this->assertEquals(1000.0, $this->vip->withdrawalLimit($user));
    }

    public function test_vip2_withdrawal_limit_defaults_to_2000(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip2]);
        $this->assertEquals(2000.0, $this->vip->withdrawalLimit($user));
    }

    public function test_vip1_fee_percent_defaults_to_3(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip1]);
        $this->assertEquals(3.0, $this->vip->feePercent($user));
    }

    public function test_vip2_fee_percent_defaults_to_1_5(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip2]);
        $this->assertEquals(1.5, $this->vip->feePercent($user));
    }

    public function test_admin_settings_override_default_limits(): void
    {
        Setting::set('vip1_withdrawal_limit', 5000);
        $user = User::factory()->create(['vip_level' => VipLevel::Vip1]);
        $this->assertEquals(5000.0, $this->vip->withdrawalLimit($user));
    }

    public function test_apply_withdrawal_within_limit_succeeds(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip1, 'balance' => 5000]);
        $result = $this->vip->applyWithdrawal(800, $user);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(800, $result['amount']);
        $this->assertEquals(24.0, $result['fee']);  // 800 * 3%
        $this->assertEquals(776.0, $result['net']);
    }

    public function test_apply_withdrawal_exceeds_limit(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip1, 'balance' => 5000]);
        $result = $this->vip->applyWithdrawal(1500, $user);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('exceeds your VIP withdrawal limit', $result['reason']);
    }

    public function test_apply_withdrawal_vip2_higher_limit(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip2, 'balance' => 5000]);
        $result = $this->vip->applyWithdrawal(1500, $user);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(22.5, $result['fee']);  // 1500 * 1.5%
    }

    public function test_apply_withdrawal_rejects_zero_amount(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::Vip1]);
        $result = $this->vip->applyWithdrawal(0, $user);
        $this->assertFalse($result['allowed']);
    }

    public function test_apply_withdrawal_regular_user_blocked(): void
    {
        $user = User::factory()->create(['vip_level' => VipLevel::None, 'balance' => 100]);
        $result = $this->vip->applyWithdrawal(50, $user);
        $this->assertFalse($result['allowed']);
    }

    public function test_admin_can_change_fee_percent_at_runtime(): void
    {
        Setting::set('vip2_fee_percent', 1.0);
        $user = User::factory()->create(['vip_level' => VipLevel::Vip2, 'balance' => 5000]);
        $result = $this->vip->applyWithdrawal(2000, $user);
        $this->assertEquals(20.0, $result['fee']);  // 2000 * 1.0%
    }
}
