<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Import as ImageImport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_manager_can_delete_project_with_images_and_imports(): void
    {
        Storage::fake('scaleway');

        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Suppression',
            'slug' => 'client-suppression',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Suppression',
            'slug' => 'projet-suppression',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'title' => 'Image suppression',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/projet-suppression/source.jpg',
            'object_key_web' => 'photos/projet-suppression/JPG/source.jpg',
            'object_key_hd' => 'photos/projet-suppression/source.jpg',
        ]);
        ImageImport::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'started_by' => $manager->id,
            'source' => 'folder_upload',
            'status' => 'completed',
            'total_items' => 1,
        ]);

        Storage::disk('scaleway')->put($image->object_key_original, 'original');
        Storage::disk('scaleway')->put($image->object_key_web, 'web');

        $this->actingAs($manager)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect()
            ->assertSessionHas('success', 'Projet supprimé.');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('images', ['id' => $image->id]);
        $this->assertDatabaseMissing('imports', ['project_id' => $project->id]);
        Storage::disk('scaleway')->assertMissing('photos/projet-suppression/source.jpg');
        Storage::disk('scaleway')->assertMissing('photos/projet-suppression/JPG/source.jpg');
    }
}
