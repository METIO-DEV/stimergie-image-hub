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
        $member = User::factory()->create([
            'name' => 'Membre Client',
            'email' => 'membre@example.test',
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('clients.members.store', $client), [
            'name' => $member->name,
            'email' => $member->email,
            'role' => 'manager',
            'status' => 'active',
            'is_default' => true,
        ])->assertRedirect();

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

    public function test_standard_user_cannot_be_assigned_owner_or_manager_role(): void
    {
        [$client, $owner] = $this->createClientWithOwner();
        $standardUser = User::factory()->create([
            'name' => 'Utilisateur Standard',
            'email' => 'standard@example.test',
            'platform_role' => 'user',
            'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('clients.members.store', $client), [
            'name' => $standardUser->name,
            'email' => $standardUser->email,
            'role' => 'manager',
            'status' => 'active',
            'is_default' => false,
        ])->assertSessionHasErrors([
            'role' => "Cet utilisateur doit d'abord etre passe en Admin Client avant de recevoir un role Owner ou Manager.",
        ]);

        $this->assertDatabaseMissing('client_memberships', [
            'client_id' => $client->id,
            'user_id' => $standardUser->id,
            'role' => 'manager',
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

    public function test_standard_user_is_redirected_away_from_client_management(): void
    {
        $user = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertRedirect(route('gallery.index'))
            ->assertSessionHas('warning', "La gestion des clients est reservee aux Admin Client owner/manager.");
    }

    public function test_client_management_lists_only_owned_or_managed_clients_for_admin_client(): void
    {
        $adminClient = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        $ownedClient = Client::create([
            'name' => 'Entreprise Owner',
            'slug' => 'entreprise-owner',
            'status' => 'active',
        ]);
        $managedClient = Client::create([
            'name' => 'Entreprise Manager',
            'slug' => 'entreprise-manager',
            'status' => 'active',
        ]);
        $viewerClient = Client::create([
            'name' => 'Entreprise Viewer',
            'slug' => 'entreprise-viewer',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $ownedClient->id,
            'user_id' => $adminClient->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        ClientMembership::create([
            'client_id' => $managedClient->id,
            'user_id' => $adminClient->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        ClientMembership::create([
            'client_id' => $viewerClient->id,
            'user_id' => $adminClient->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->actingAs($adminClient)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clients/Index')
                ->has('clients', 2)
                ->where('clients.0.id', $managedClient->id)
                ->where('clients.1.id', $ownedClient->id)
                ->etc());
    }

    /**
     * @return array{Client, User, ClientMembership}
     */
    private function createClientWithOwner(): array
    {
        $owner = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
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
