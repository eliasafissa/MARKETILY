<?php
namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketllyCompanyLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_arbitrary_http_whatsapp_link_and_public_api_returns_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $link = 'https://example.org/support?src=marketlly&chat=1';
        $this->actingAs($admin)->putJson('/api/admin/settings/company', [
            'whatsapp_url' => $link,
        ])->assertOk();
        $this->assertSame($link, Setting::where('key', 'whatsapp_url')->value('value'));
        $this->getJson('/api/settings/company')->assertOk()->assertJsonPath('settings.whatsapp_url', $link);
    }
}
