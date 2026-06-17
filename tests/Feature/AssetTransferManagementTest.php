<?php

namespace Tests\Feature;

use App\Jobs\RunAssetTransferJob;
use App\Jobs\RunBucketDatabaseSyncJob;
use App\Jobs\RunMissingWebVariantGenerationJob;
use App\Models\AssetTransferJob;
use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use App\Support\O2SwitchAssetBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AssetTransferManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_asset_transfer_interface(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('asset-transfers.index'))
            ->assertOk();
    }

    public function test_standard_user_cannot_open_asset_transfer_interface(): void
    {
        $user = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('asset-transfers.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_start_transfer_from_selected_folders(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Adamance',
            'slug' => 'adamance',
            'status' => 'active',
        ]);
        Project::create([
            'client_id' => $client->id,
            'name' => 'ADAMANCE ESSENTIELS',
            'slug' => 'adamance-essentiels',
            'source_folder' => 'ADAMANCE_ESSENTIELS',
            'status' => 'active',
        ]);
        Project::create([
            'client_id' => $client->id,
            'name' => 'IMPRONONCABLE',
            'slug' => 'imprononcable',
            'source_folder' => 'IMPRONONCABLE',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.store'), [
                'folders' => ['ADAMANCE_ESSENTIELS', 'IMPRONONCABLE'],
            ])
            ->assertCreated()
            ->assertJsonPath('job.status', 'pending')
            ->assertJsonPath('job.totalFolders', 2);

        $this->assertDatabaseHas('asset_transfer_jobs', [
            'started_by' => $admin->id,
            'status' => 'pending',
            'total_folders' => 2,
        ]);
        Queue::assertPushed(RunAssetTransferJob::class);
    }

    public function test_selected_transfer_rejects_folders_without_project_mapping(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.store'), [
                'folders' => ['DOSSIER_SANS_PROJET'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La sélection contient un dossier sans projet associé.');

        Queue::assertNotPushed(RunAssetTransferJob::class);
    }

    public function test_selected_transfer_rejects_non_transferable_folders(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.store'), [
                'folders' => ['Destination: scaleway:stimergie/photos'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La sélection contient un dossier non transférable.');

        Queue::assertNotPushed(RunAssetTransferJob::class);
    }

    public function test_super_admin_can_start_transfer_from_first_missing_folders(): void
    {
        Queue::fake();

        $this->instance(O2SwitchAssetBrowser::class, new class extends O2SwitchAssetBrowser
        {
            public function ftpFolders(int $limit = 1000): array
            {
                return ['ALREADY_BUCKET', 'MISSING_ONE', 'MISSING_TWO'];
            }

            public function bucketFolders(string $prefix = 'photos'): array
            {
                return ['ALREADY_BUCKET' => 12];
            }
        });

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Missing',
            'slug' => 'client-missing',
            'status' => 'active',
        ]);
        Project::create([
            'client_id' => $client->id,
            'name' => 'Missing One',
            'slug' => 'missing-one',
            'source_folder' => 'MISSING_ONE',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.store'), [
                'limit' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('job.totalFolders', 1)
            ->assertJsonPath('job.folders.0', 'MISSING_ONE');

        $job = AssetTransferJob::query()->firstOrFail();

        $this->assertSame(['MISSING_ONE'], $job->folders);
        Queue::assertPushed(RunAssetTransferJob::class);
    }

    public function test_limited_transfer_skips_missing_ftp_folders_without_project_mapping(): void
    {
        Queue::fake();

        $this->instance(O2SwitchAssetBrowser::class, new class extends O2SwitchAssetBrowser
        {
            public function ftpFolders(int $limit = 1000): array
            {
                return ['MISSING_WITHOUT_PROJECT'];
            }

            public function bucketFolders(string $prefix = 'photos'): array
            {
                return [];
            }
        });

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.store'), [
                'limit' => 1,
            ])
            ->assertUnprocessable()
            ->assertSee('Aucun dossier FTP manquant ne correspond à un projet associé.');

        Queue::assertNotPushed(RunAssetTransferJob::class);
    }

    public function test_super_admin_can_start_bucket_database_sync_while_transfer_is_running(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        AssetTransferJob::create([
            'started_by' => $admin->id,
            'status' => 'running',
            'mode' => 'batch-copy',
            'total_folders' => 20,
            'processed_folders' => 3,
            'folders' => ['COMPAS_SHOOT EXALT 071025'],
            'completed_folders' => [],
            'failed_folder_details' => [],
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.resync-bucket'))
            ->assertCreated()
            ->assertJsonPath('job.status', 'pending')
            ->assertJsonPath('job.mode', 'bucket-db-sync');

        Queue::assertPushedOn('sync', RunBucketDatabaseSyncJob::class);

        $this->assertDatabaseHas('asset_transfer_jobs', [
            'started_by' => $admin->id,
            'status' => 'pending',
            'mode' => 'bucket-db-sync',
        ]);
    }

    public function test_super_admin_can_start_web_variant_generation_for_project(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Web',
            'slug' => 'client-web',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Web',
            'slug' => 'projet-web',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.generate-web-variants'), [
                'project_id' => $project->id,
            ])
            ->assertCreated()
            ->assertJsonPath('job.status', 'pending')
            ->assertJsonPath('job.mode', 'web-variant-generation')
            ->assertJsonPath('job.webVariantTotals.checked', 0);

        $this->assertDatabaseHas('asset_transfer_jobs', [
            'started_by' => $admin->id,
            'status' => 'pending',
            'mode' => 'web-variant-generation',
        ]);
        Queue::assertPushedOn('sync', RunMissingWebVariantGenerationJob::class);
    }

    public function test_super_admin_can_map_and_ignore_asset_folders(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client',
            'slug' => 'client',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet associé',
            'slug' => 'projet-associe',
            'source_folder' => 'PROJET_ASSOCIE',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.folder-mappings.store'), [
                'folder' => 'DOSSIER_BUCKET_SANS_PROJET',
                'project_id' => $project->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('asset_folder_mappings', [
            'folder' => 'DOSSIER_BUCKET_SANS_PROJET',
            'project_id' => $project->id,
            'status' => 'mapped',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.folder-mappings.ignore'), [
                'folder' => 'DOSSIER_A_IGNORER',
            ])
            ->assertOk();

        $this->assertDatabaseHas('asset_folder_mappings', [
            'folder' => 'DOSSIER_A_IGNORER',
            'project_id' => null,
            'status' => 'ignored',
        ]);
    }

    public function test_super_admin_can_auto_map_high_confidence_asset_folders(): void
    {
        $this->instance(O2SwitchAssetBrowser::class, new class extends O2SwitchAssetBrowser
        {
            public function ftpFolders(int $limit = 1000): array
            {
                return ['COMPAS_SHOOT EXALT 071025 HD'];
            }

            public function bucketFolders(string $prefix = 'photos'): array
            {
                return ['COMPAS_SHOOT EXALT 071025 HD' => 12];
            }
        });

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Compas',
            'slug' => 'compas',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'COMPAS SHOOT EXALT 071025',
            'slug' => 'compas-shoot-exalt-071025',
            'source_folder' => 'COMPAS_SHOOT EXALT_071025',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('asset-transfers.folder-mappings.auto'))
            ->assertOk()
            ->assertJsonPath('mapped', 1);

        $this->assertDatabaseHas('asset_folder_mappings', [
            'folder' => 'COMPAS_SHOOT EXALT 071025 HD',
            'project_id' => $project->id,
            'status' => 'mapped',
        ]);
    }

    public function test_sources_report_projects_missing_web_variants(): void
    {
        $this->instance(O2SwitchAssetBrowser::class, new class extends O2SwitchAssetBrowser
        {
            public function ftpFolders(int $limit = 1000): array
            {
                return ['DOSSIER_WEB_MANQUANT'];
            }

            public function bucketFolders(string $prefix = 'photos'): array
            {
                return ['DOSSIER_WEB_MANQUANT' => 2];
            }
        });

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Audit',
            'slug' => 'client-audit',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Web Manquant',
            'slug' => 'projet-web-manquant',
            'source_folder' => 'DOSSIER_WEB_MANQUANT',
            'status' => 'active',
        ]);

        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Sans web',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/DOSSIER_WEB_MANQUANT/source.jpg',
            'object_key_web' => null,
            'object_key_hd' => 'photos/DOSSIER_WEB_MANQUANT/source.jpg',
        ]);
        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Web pointe original',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/DOSSIER_WEB_MANQUANT/heavy.jpg',
            'object_key_web' => 'photos/DOSSIER_WEB_MANQUANT/heavy.jpg',
            'object_key_hd' => 'photos/DOSSIER_WEB_MANQUANT/heavy.jpg',
        ]);
        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Web OK',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/DOSSIER_WEB_MANQUANT/original.jpg',
            'object_key_web' => 'photos/DOSSIER_WEB_MANQUANT/JPG/original.jpg',
            'object_key_hd' => 'photos/DOSSIER_WEB_MANQUANT/original.jpg',
        ]);

        $this->actingAs($admin)
            ->getJson(route('asset-transfers.sources'))
            ->assertOk()
            ->assertJsonPath('folders.0.projectId', $project->id)
            ->assertJsonPath('folders.0.missingWebVariantCount', 2)
            ->assertJsonPath('folders.0.webVariantReadyCount', 1)
            ->assertJsonPath('webVariantAudits.0.projectId', $project->id)
            ->assertJsonPath('webVariantAudits.0.missingWebVariantCount', 2);
    }
}
