<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessPeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_update_and_delete_access_period(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Acces',
            'slug' => 'client-acces',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Acces',
            'slug' => 'projet-acces',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('access-periods.store'), [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-30',
            'is_active' => true,
        ])->assertRedirect();

        $period = ProjectAccessPeriod::firstOrFail();

        $this->assertDatabaseHas('project_access_periods', [
            'id' => $period->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('access-periods.update', $period), [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_access_periods', [
            'id' => $period->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('access-periods.destroy', $period))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_access_periods', [
            'id' => $period->id,
        ]);
    }

    public function test_client_manager_cannot_create_period_for_unmanaged_project(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
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
        $otherProject = Project::create([
            'client_id' => $otherClient->id,
            'name' => 'Projet Autre',
            'slug' => 'projet-autre',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $managedClient->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->post(route('access-periods.store'), [
            'client_id' => $managedClient->id,
            'project_id' => $otherProject->id,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-30',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_access_period_validation_requires_end_after_start(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Dates',
            'slug' => 'client-dates',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Dates',
            'slug' => 'projet-dates',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('access-periods.store'), [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'starts_at' => '2026-06-30',
            'ends_at' => '2026-06-01',
            'is_active' => true,
        ])->assertInvalid('ends_at');
    }
}
