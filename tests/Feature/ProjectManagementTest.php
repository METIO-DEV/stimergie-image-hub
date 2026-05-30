<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_a_project(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Projet',
            'slug' => 'client-projet',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Campagne Printemps',
            'client_id' => $client->id,
            'type' => 'shooting',
            'source_folder' => '2026-printemps',
            'status' => 'active',
        ])->assertRedirect();

        $project = Project::query()->where('slug', 'campagne-printemps')->firstOrFail();

        $this->actingAs($admin)->patch(route('projects.update', $project), [
            'name' => 'Campagne Ete',
            'client_id' => $client->id,
            'type' => 'social',
            'source_folder' => '2026-ete',
            'status' => 'paused',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Campagne Ete',
            'slug' => 'campagne-ete',
            'type' => 'social',
            'status' => 'paused',
        ]);
    }

    public function test_client_manager_can_only_create_project_for_accessible_client(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $managedClient = Client::create([
            'name' => 'Client Gere',
            'slug' => 'client-gere',
            'status' => 'active',
        ]);
        $otherClient = Client::create([
            'name' => 'Autre Client',
            'slug' => 'autre-client',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $managedClient->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->post(route('projects.store'), [
            'name' => 'Projet Autorise',
            'client_id' => $managedClient->id,
            'type' => '',
            'source_folder' => '',
            'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('projects.store'), [
            'name' => 'Projet Refuse',
            'client_id' => $otherClient->id,
            'type' => '',
            'source_folder' => '',
            'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'client_id' => $managedClient->id,
            'slug' => 'projet-autorise',
        ]);
        $this->assertDatabaseMissing('projects', [
            'client_id' => $otherClient->id,
            'slug' => 'projet-refuse',
        ]);
    }
}
