<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_business_operational_tracking(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientProject('Client Suivi', 'Projet Suivi');
        $image = $this->image($client, $project, [
            'title' => 'Image cession',
            'object_key_web' => 'photos/projet-suivi/web/image-cession.jpg',
            'rights_starts_at' => now()->subMonth()->toDateString(),
            'rights_ends_at' => now()->addDays(10)->toDateString(),
        ]);

        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'title' => 'Archive suivi',
            'status' => 'ready',
            'image_count' => 1,
            'is_hd' => false,
            'payload' => [
                'variant' => 'web',
                'requested_image_ids' => [$image->id],
            ],
            'processed_at' => now(),
            'download_url_expires_at' => now()->addDays(7),
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
            'ends_at' => now()->addMonth(),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('summary.downloadsLast30Days', 1)
                ->where('summary.downloadedImagesLast30Days', 1)
                ->where('summary.openExtensionRequests', 1)
                ->where('summary.expiringRights', 1)
                ->where('summary.activeAccessPeriods', 1)
                ->where('downloads.total', 1)
                ->where('downloads.items.0.title', 'Archive suivi')
                ->where('downloads.items.0.actorName', $admin->name)
                ->where('downloads.items.0.images.0.title', 'Image cession')
                ->where('downloads.items.0.images.0.thumbUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=web'))
                ->where('downloads.items.0.images.0.imageUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=display'))
                ->where('rights.total', 1)
                ->where('rights.items.0.status', 'expiring_soon')
                ->where('rights.items.0.thumbUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=web'))
                ->where('rights.items.0.imageUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=display'))
                ->where('extensionRequests.total', 1)
                ->where('extensionRequests.items.0.imageTitle', 'Image cession')
                ->where('extensionRequests.items.0.thumbUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=web'))
                ->where('extensionRequests.items.0.imageUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=display'))
                ->where('accessPeriods.total', 1)
                ->where('accessPeriods.items.0.status', 'active')
                ->where('accessPeriods.items.0.images.0.title', 'Image cession')
                ->where('accessPeriods.items.0.images.0.thumbUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=web'))
                ->where('accessPeriods.items.0.images.0.imageUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=display'))
                ->etc());
    }

    public function test_only_super_admin_can_access_operational_tracking(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        [$client] = $this->clientProject('Client Manager', 'Projet Manager');

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->get(route('operations.index'))
            ->assertForbidden();
    }

    public function test_operational_tracking_filters_downloads_by_user_and_project_images(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        [$visibleClient, $visibleProject] = $this->clientProject('Client Visible', 'Projet Visible');
        [$hiddenClient, $hiddenProject] = $this->clientProject('Client Cache', 'Projet Cache');
        $visibleImage = $this->image($visibleClient, $visibleProject, ['title' => 'Image visible']);
        $hiddenImage = $this->image($hiddenClient, $hiddenProject, ['title' => 'Image cachee']);

        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $visibleClient->id,
            'title' => 'Archive visible',
            'status' => 'ready',
            'image_count' => 1,
            'payload' => [
                'variant' => 'hd',
                'requested_image_ids' => [$visibleImage->id],
            ],
        ]);
        DownloadJob::create([
            'user_id' => $otherUser->id,
            'client_id' => $hiddenClient->id,
            'title' => 'Archive cachee',
            'status' => 'ready',
            'image_count' => 1,
            'payload' => [
                'variant' => 'hd',
                'requested_image_ids' => [$hiddenImage->id],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('operations.index', [
                'user_id' => $admin->id,
                'project_id' => $visibleProject->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('downloads.total', 1)
                ->where('downloads.items.0.title', 'Archive visible')
                ->where('downloads.items.0.projectName', 'Projet Visible')
                ->where('activeFilters.userId', (string) $admin->id)
                ->where('activeFilters.projectId', (string) $visibleProject->id)
                ->etc());
    }

    public function test_operational_tracking_filters_rights_and_access_period_statuses(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientProject('Client Droits', 'Projet Droits');
        $expiredImage = $this->image($client, $project, [
            'title' => 'Image expiree',
            'rights_ends_at' => now()->subDay()->toDateString(),
        ]);
        $this->image($client, $project, [
            'title' => 'Image active',
            'rights_ends_at' => now()->addYear()->toDateString(),
        ]);
        ProjectAccessPeriod::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('operations.index', [
                'status' => 'expired',
                'client_id' => $client->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('rights.total', 1)
                ->where('rights.items.0.id', $expiredImage->id)
                ->where('rights.items.0.status', 'expired')
                ->where('accessPeriods.total', 1)
                ->where('accessPeriods.items.0.status', 'expired')
                ->where('activeFilters.status', 'expired')
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
