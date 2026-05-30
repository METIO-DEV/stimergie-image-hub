<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkImageProjectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_assign_selected_images_to_a_project(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$sourceClient, $sourceProject] = $this->clientAndProject('Source');
        [$targetClient, $targetProject] = $this->clientAndProject('Target');
        $image = Image::create([
            'client_id' => $sourceClient->id,
            'project_id' => $sourceProject->id,
            'title' => 'Image source',
            'status' => 'ready',
        ]);

        $this->actingAs($admin)->patch(route('images.bulk-project'), [
            'project_id' => $targetProject->id,
            'image_ids' => [$image->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('images', [
            'id' => $image->id,
            'client_id' => $targetClient->id,
            'project_id' => $targetProject->id,
        ]);
    }

    public function test_client_manager_must_manage_source_and_target_clients(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        [$sourceClient, $sourceProject] = $this->clientAndProject('Source');
        [, $targetProject] = $this->clientAndProject('Target');
        $image = Image::create([
            'client_id' => $sourceClient->id,
            'project_id' => $sourceProject->id,
            'title' => 'Image source',
            'status' => 'ready',
        ]);

        ClientMembership::create([
            'client_id' => $sourceClient->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->patch(route('images.bulk-project'), [
            'project_id' => $targetProject->id,
            'image_ids' => [$image->id],
        ])->assertForbidden();
    }

    /**
     * @return array{Client, Project}
     */
    private function clientAndProject(string $name): array
    {
        $slug = strtolower($name);
        $client = Client::create([
            'name' => "Client {$name}",
            'slug' => "client-{$slug}",
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => "Projet {$name}",
            'slug' => "projet-{$slug}",
            'status' => 'active',
        ]);

        return [$client, $project];
    }
}
