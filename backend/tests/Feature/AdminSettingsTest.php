<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson('/api/admin/settings', [
            'key' => 'vip1_withdrawal_limit',
            'value' => '5000',
            'type' => 'float',
        ])->assertOk();

        $this->assertEquals(5000.0, Setting::get('vip1_withdrawal_limit'));
    }

    public function test_moderator_cannot_update_settings(): void
    {
        $mod = User::factory()->create();
        $mod->assignRole('moderator');

        $this->actingAs($mod)->postJson('/api/admin/settings', [
            'key' => 'vip1_withdrawal_limit',
            'value' => '5000',
            'type' => 'float',
        ])->assertStatus(403);
    }

    public function test_admin_can_bulk_update_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson('/api/admin/settings/bulk', [
            'items' => [
                ['key' => 'vip1_withdrawal_limit', 'value' => '1500', 'type' => 'float'],
                ['key' => 'vip2_withdrawal_limit', 'value' => '3000', 'type' => 'float'],
            ],
        ])->assertOk();

        $this->assertEquals(1500.0, Setting::get('vip1_withdrawal_limit'));
        $this->assertEquals(3000.0, Setting::get('vip2_withdrawal_limit'));
    }
}
