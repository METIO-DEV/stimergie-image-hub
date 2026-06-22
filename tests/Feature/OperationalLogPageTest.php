<?php

namespace Tests\Feature;

use App\Models\AssetTransferJob;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Import;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_paginated_technical_tracking(): void
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
        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'title' => 'Archive suivi',
            'status' => 'failed',
            'image_count' => 3,
            'error_details' => 'Archive impossible à écrire',
        ]);
        Import::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'started_by' => $admin->id,
            'source' => 'folder_upload',
            'status' => 'failed',
            'total_items' => 4,
            'processed_items' => 3,
            'failed_items' => 1,
        ]);
        AssetTransferJob::create([
            'started_by' => $admin->id,
            'status' => 'failed',
            'mode' => 'batch-copy',
            'total_folders' => 2,
            'processed_folders' => 1,
            'failed_folders' => 1,
            'failed_folder_details' => [['folder' => 'ADAMANCE', 'error' => 'Timeout']],
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-job-uuid',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => "RuntimeException: Job cassé\nStack trace",
            'failed_at' => now(),
        ]);
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
                ->where('stats.auditLogs', 1)
                ->where('stats.openRightsRequests', 1)
                ->where('stats.failedDownloads', 1)
                ->where('stats.failedImports', 1)
                ->where('stats.failedTransfers', 1)
                ->where('stats.failedJobs', 1)
                ->where('canViewSensitiveAuditData', true)
                ->where('pagination.total', 6)
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

        AuditLog::create([
            'actor_id' => $manager->id,
            'client_id' => $visibleClient->id,
            'action' => 'visible.trace',
            'subject_type' => Project::class,
            'subject_id' => $visibleProject->id,
        ]);
        AuditLog::create([
            'actor_id' => $manager->id,
            'client_id' => $hiddenClient->id,
            'action' => 'hidden.trace',
            'subject_type' => Project::class,
            'subject_id' => $hiddenProject->id,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('operations.index', ['type' => 'audit']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('canViewSensitiveAuditData', false)
                ->has('events', 1)
                ->where('events.0.title', 'visible.trace')
                ->where('events.0.clientName', 'Client Visible')
                ->where('activeFilters.type', 'audit')
                ->etc());

        $response->assertDontSee('hidden.trace');
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

    public function test_operational_tracking_filters_by_type_status_and_paginates(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientProject('Client Filtre', 'Projet Filtre');

        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'title' => 'Archive erreur',
            'status' => 'failed',
            'image_count' => 1,
            'error_details' => 'Zip failed',
        ]);
        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'title' => 'Archive prête',
            'status' => 'ready',
            'image_count' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('operations.index', [
                'type' => 'telechargement',
                'status' => 'failed',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('pagination.total', 1)
                ->where('pagination.perPage', 10)
                ->has('events', 1)
                ->where('events.0.title', 'Archive erreur')
                ->where('events.0.status', 'failed')
                ->where('events.0.severity', 'error')
                ->where('activeFilters.type', 'telechargement')
                ->where('activeFilters.status', 'failed')
                ->etc());

        $response->assertDontSee('Archive prête');
    }

    public function test_operational_tracking_card_view_lists_failed_download_details(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientProject('Client Vue', 'Projet Vue');
        $firstImage = $this->image($client, $project, ['title' => 'Image A']);
        $secondImage = $this->image($client, $project, ['title' => 'Image B']);

        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'title' => 'Archive export story',
            'status' => 'failed',
            'image_count' => 2,
            'error_details' => 'Aucune source disponible',
            'payload' => [
                'variant' => 'crop',
                'crop_preset' => 'story',
                'crop_source' => 'web',
                'requested_image_ids' => [$firstImage->id, $secondImage->id],
                'skipped_images' => [
                    ['id' => $secondImage->id, 'title' => $secondImage->title],
                ],
            ],
        ]);
        DownloadJob::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'title' => 'Archive prête',
            'status' => 'ready',
            'image_count' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('operations.index', ['view' => 'failed_downloads']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('pagination.total', 1)
                ->where('activeFilters.view', 'failed_downloads')
                ->has('events', 1)
                ->where('events.0.title', 'Archive export story')
                ->where('events.0.metadata.format', 'Export story')
                ->where('events.0.metadata.requestedImages', "#{$firstImage->id} Image A - Client Vue / Projet Vue ; #{$secondImage->id} Image B - Client Vue / Projet Vue")
                ->where('events.0.metadata.skippedImages', "#{$secondImage->id} Image B")
                ->etc());

        $response->assertDontSee('Archive prête');
    }

    public function test_operational_tracking_no_longer_lists_access_periods(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/Index')
                ->where('filters.types', fn ($types) => collect($types)->pluck('value')->doesntContain('droits_acces'))
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
