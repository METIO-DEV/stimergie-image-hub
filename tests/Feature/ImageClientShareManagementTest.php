<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImageClientShareManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_share_image_with_another_client(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$sourceClient, $project, $image] = $this->imageFixture();
        $targetClient = Client::create([
            'name' => 'Client cible',
            'slug' => 'client-cible',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('images.client-shares.store', $image), [
                'client_id' => $targetClient->id,
            ])
            ->assertCreated()
            ->assertJsonPath('sharedClients.0.id', $targetClient->id);

        $this->assertDatabaseHas('image_client_shares', [
            'image_id' => $image->id,
            'client_id' => $targetClient->id,
            'created_by' => $admin->id,
        ]);

        $viewer = User::factory()->create(['status' => 'active']);
        ClientMembership::create([
            'client_id' => $targetClient->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('images.0.id', $image->id)
                ->where('images.0.clientId', $sourceClient->id)
                ->etc());
    }

    public function test_client_manager_cannot_share_image_with_unmanaged_client(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        [$sourceClient, , $image] = $this->imageFixture();
        $targetClient = Client::create([
            'name' => 'Client externe',
            'slug' => 'client-externe',
            'status' => 'active',
        ]);
        ClientMembership::create([
            'client_id' => $sourceClient->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->postJson(route('images.client-shares.store', $image), [
                'client_id' => $targetClient->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('image_client_shares', 0);
    }

    /**
     * @return array{Client, Project, Image}
     */
    private function imageFixture(): array
    {
        $client = Client::create([
            'name' => 'Client source',
            'slug' => 'client-source',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet source',
            'slug' => 'projet-source',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image partagée',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-source/source.jpg',
        ]);

        return [$client, $project, $image];
    }
}
