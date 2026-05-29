<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_client_and_becomes_owner(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('clients.store'), [
            'name' => 'Nouveau Client',
            'slug' => '',
            'status' => 'active',
        ]);

        $client = Client::query()->where('slug', 'nouveau-client')->firstOrFail();

        $response->assertRedirect(route('clients.show', $client));
        $this->assertDatabaseHas('client_memberships', [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_owner_can_manage_client_members(): void
    {
        [$client, $owner] = $this->createClientWithOwner();

        $this->actingAs($owner)->post(route('clients.members.store', $client), [
            'name' => 'Membre Client',
            'email' => 'membre@example.test',
            'role' => 'manager',
            'status' => 'active',
            'is_default' => true,
        ])->assertRedirect();

        $member = User::query()->where('email', 'membre@example.test')->firstOrFail();
        $membership = ClientMembership::query()
            ->where('client_id', $client->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->assertTrue($membership->is_default);

        $this->actingAs($owner)->patch(route('clients.members.update', [$client, $membership]), [
            'role' => 'viewer',
            'status' => 'paused',
            'is_default' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('client_memberships', [
            'id' => $membership->id,
            'role' => 'viewer',
            'status' => 'paused',
            'is_default' => false,
        ]);

        $this->actingAs($owner)
            ->delete(route('clients.members.destroy', [$client, $membership]))
            ->assertRedirect();

        $this->assertDatabaseMissing('client_memberships', [
            'id' => $membership->id,
        ]);
    }

    public function test_client_keeps_at_least_one_active_owner(): void
    {
        [$client, $owner, $membership] = $this->createClientWithOwner();

        $this->actingAs($owner)
            ->delete(route('clients.members.destroy', [$client, $membership]))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('client_memberships', [
            'id' => $membership->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    /**
     * @return array{Client, User, ClientMembership}
     */
    private function createClientWithOwner(): array
    {
        $owner = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Test',
            'slug' => 'client-test',
            'status' => 'active',
        ]);

        $membership = ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return [$client, $owner, $membership];
    }
}
