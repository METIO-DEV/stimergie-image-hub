<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\SharedAlbum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_consolidated_operational_tracking(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientProject('Client Suivi', 'Projet Suivi');
        $image = $this->image($client, $project, [
            'title' => 'Image droits expires',
            'rights_starts_at' => now()->subYear(),
            'rights_ends_at' => now()->subDay(),
        ]);

        ImageRightsExtensionRequest::create([
            'image_id' => $image->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'requested_by' => $admin->id,
            'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
            'rights_ends_at' => $image->rights_ends_at,
        ]);
        ProjectAccessPeriod::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'title' => 'Archive suivi',
            'status' => 'failed',
            'image_count' => 3,
        ]);
        SharedAlbum::create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'name' => 'Album suivi',
            'share_key' => 'album-suivi',
            'expires_at' => now()->addDays(20),
            'is_active' => true,
        ])->images()->attach($image->id);
        AuditLog::create([
            'actor_id' => $admin->id,
            'client_id' => $client->id,
            'action' => 'rights_extension.created',
            'subject_type' => ImageRightsExtensionRequest::class,
            'subject_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
        ]);

        $this->actingAs($admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('stats.rightsExpired', 1)
                ->where('stats.openRightsRequests', 1)
                ->where('stats.accessEndingSoon', 1)
                ->where('stats.failedDownloads', 1)
                ->where('stats.activeSharedAlbums', 1)
                ->where('canViewSensitiveAuditData', true)
                ->has('events', 6)
                ->etc());
    }

    public function test_client_manager_only_sees_operational_events_for_managed_clients(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        [$visibleClient, $visibleProject] = $this->clientProject('Client Visible', 'Projet Visible');
        [$hiddenClient, $hiddenProject] = $this->clientProject('Client Cache', 'Projet Cache');

        ClientMembership::create([
            'client_id' => $visibleClient->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->image($visibleClient, $visibleProject, [
            'title' => 'Cession visible',
            'rights_ends_at' => now()->subDay(),
        ]);
        $this->image($hiddenClient, $hiddenProject, [
            'title' => 'Cession cachee',
            'rights_ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($manager)
            ->get(route('operations.index', ['type' => 'cession']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('canViewSensitiveAuditData', false)
                ->has('events', 1)
                ->where('events.0.title', 'Cession visible')
                ->where('events.0.clientName', 'Client Visible')
                ->where('activeFilters.type', 'cession')
                ->etc());

        $response->assertDontSee('Cession cachee');
        $response->assertDontSee('Client Cache');
    }

    public function test_viewer_cannot_access_operational_tracking(): void
    {
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        [$client] = $this->clientProject('Client Viewer', 'Projet Viewer');

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->get(route('operations.index'))
            ->assertForbidden();
    }

    public function test_operational_tracking_filters_by_type_and_status(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientProject('Client Filtre', 'Projet Filtre');

        $this->image($client, $project, [
            'title' => 'Cession expiree',
            'rights_ends_at' => now()->subDay(),
        ]);
        $this->image($client, $project, [
            'title' => 'Cession active',
            'rights_ends_at' => now()->addMonths(3),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('operations.index', [
                'type' => 'cession',
                'status' => 'expired',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->has('events', 1)
                ->where('events.0.title', 'Cession expiree')
                ->where('events.0.status', 'expired')
                ->where('activeFilters.type', 'cession')
                ->where('activeFilters.status', 'expired')
                ->etc());

        $response->assertDontSee('Cession active');
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
