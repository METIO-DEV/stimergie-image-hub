<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RightsExtensionRequestTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_viewer_can_follow_rights_extension_requests(): void
    {
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientProject('Client Suivi', 'Projet Suivi');
        $image = $this->image($client, $project, [
            'title' => 'Image suivie',
            'rights_ends_at' => now()->subDay(),
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        ImageRightsExtensionRequest::create([
            'image_id' => $image->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'requested_by' => $viewer->id,
            'status' => ImageRightsExtensionRequest::STATUS_IN_PROGRESS,
            'rights_ends_at' => $image->rights_ends_at,
        ]);

        $this->actingAs($viewer)
            ->get(route('rights-extension-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RightsExtensions/Index')
                ->has('requests', 1)
                ->where('requests.0.imageTitle', 'Image suivie')
                ->where('requests.0.status', ImageRightsExtensionRequest::STATUS_IN_PROGRESS)
                ->where('requests.0.clientName', 'Client Suivi')
                ->etc());
    }

    public function test_client_viewer_does_not_see_other_client_requests(): void
    {
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create(['status' => 'active']);
        [$visibleClient, $visibleProject] = $this->clientProject('Client Visible', 'Projet Visible');
        [$hiddenClient, $hiddenProject] = $this->clientProject('Client Cache', 'Projet Cache');
        $visibleImage = $this->image($visibleClient, $visibleProject, [
            'title' => 'Image visible',
            'rights_ends_at' => now()->subDay(),
            'rights_extension_requested_at' => now()->subDays(2),
            'rights_extension_requested_by' => $viewer->id,
        ]);
        $this->image($hiddenClient, $hiddenProject, [
            'title' => 'Image cachee',
            'rights_ends_at' => now()->subDay(),
            'rights_extension_requested_at' => now()->subDays(2),
            'rights_extension_requested_by' => $otherUser->id,
        ]);

        ClientMembership::create([
            'client_id' => $visibleClient->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('rights-extension-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RightsExtensions/Index')
                ->has('requests', 1)
                ->where('requests.0.id', "legacy-image-{$visibleImage->id}")
                ->where('requests.0.imageTitle', 'Image visible')
                ->where('requests.0.isLegacy', true)
                ->etc());

        $response->assertDontSee('Image cachee');
        $response->assertDontSee('Client Cache');
    }

    public function test_client_viewer_can_follow_own_request_outside_membership_scope(): void
    {
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        [$memberClient] = $this->clientProject('Client Membre', 'Projet Membre');
        [$sharedClient, $sharedProject] = $this->clientProject('Client Partage', 'Projet Partage');
        $sharedImage = $this->image($sharedClient, $sharedProject, [
            'title' => 'Image partage demandee',
            'rights_ends_at' => now()->subDay(),
        ]);

        ClientMembership::create([
            'client_id' => $memberClient->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        ImageRightsExtensionRequest::create([
            'image_id' => $sharedImage->id,
            'client_id' => $sharedClient->id,
            'project_id' => $sharedProject->id,
            'requested_by' => $viewer->id,
            'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
            'rights_ends_at' => $sharedImage->rights_ends_at,
        ]);

        $this->actingAs($viewer)
            ->get(route('rights-extension-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RightsExtensions/Index')
                ->has('requests', 1)
                ->where('requests.0.imageTitle', 'Image partage demandee')
                ->where('requests.0.clientName', 'Client Partage')
                ->etc());
    }

    private function clientProject(string $clientName, string $projectName): array
    {
        $client = Client::create([
            'name' => $clientName,
            'slug' => str($clientName)->slug()->toString(),
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => $projectName,
            'slug' => str($projectName)->slug()->toString(),
            'status' => 'active',
        ]);

        return [$client, $project];
    }

    private function image(Client $client, Project $project, array $overrides = []): Image
    {
        return Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => $overrides['title'] ?? 'Image suivi',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/'.str($client->slug)->slug().'/source-'.uniqid().'.jpg',
            ...$overrides,
        ]);
    }
}
