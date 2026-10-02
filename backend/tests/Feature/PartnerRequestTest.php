<?php

namespace Tests\Feature;

use App\Models\PartnerApiRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_partner_request_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/partner-request');
        $response->assertStatus(401);
    }

    public function test_create_partner_request_authenticated_creates_pending_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/partner-request', [
            'store_name' => 'Test Store',
            'store_url' => 'https://teststore.com',
            'phone' => '+1 555 123 4567',
            'notes' => 'Test notes',
        ]);

        $response->assertOk(); // Controller returns 200, not 201
        $response->assertJson(['ok' => true]);
        $this->assertDatabaseHas('partner_api_requests', [
            'user_id' => $user->id,
            'store_name' => 'Test Store',
            'status' => 'pending',
        ]);
    }

    public function test_create_duplicate_partner_request_fails(): void
    {
        $user = User::factory()->create();
        PartnerApiRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->actingAs($user);

        $response = $this->postJson('/api/partner-request', [
            'store_name' => 'Another Store',
            'store_url' => 'https://another.com',
            'phone' => '+1 555 987 6543',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false, 'error' => 'already_requested']);
    }

    public function test_get_partner_request_returns_user_request_and_api_key_when_approved(): void
    {
        $user = User::factory()->create(['api_key' => 'test-api-key-123']);
        PartnerApiRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);
        $this->actingAs($user);

        $response = $this->getJson('/api/partner-request');

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'request', 'api_key']);
        $response->assertJsonPath('api_key', 'test-api-key-123');
    }

    public function test_admin_approve_partner_request_sets_api_key_on_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $request = PartnerApiRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin);
        $response = $this->postJson("/api/admin/partner-requests/{$request->id}/approve");

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'api_key']);
        $this->assertNotNull($response->json('api_key'));
        $this->assertEquals(64, strlen($response->json('api_key')));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'api_key' => $response->json('api_key')]);
        $this->assertDatabaseHas('partner_api_requests', ['id' => $request->id, 'status' => 'approved']);
    }
}