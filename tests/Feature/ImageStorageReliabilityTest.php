<?php

namespace Tests\Feature;

use App\Jobs\RunMissingWebVariantGenerationJob;
use App\Models\AssetTransferJob;
use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use App\Support\ImageUrlResolver;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectImageStoragePath;
use App\Support\ProjectImageVariantConvention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageStorageReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_maps_full_legacy_photo_path_without_ambiguous_basename_fallback(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();

        $mapped = $this->image($client, $project, [
            'legacy_id' => 'legacy-1',
            'legacy_url' => 'https://www.stimergie.fr/assets/photos/ADAMANCE_190224/JPG/photo%20test.jpg?cache=1',
        ]);
        $ambiguous = $this->image($client, $project, [
            'legacy_id' => 'legacy-2',
            'legacy_url' => 'https://www.stimergie.fr/uploads/photo-test.jpg',
        ]);

        Storage::disk('scaleway')->put('photos/ADAMANCE_190224/JPG/photo test.jpg', 'legacy-content');
        Storage::disk('scaleway')->put('photos/photo-test.jpg', 'ambiguous-content');

        $this->artisan('images:reconcile-scaleway-assets', ['--prefix' => 'photos'])
            ->assertExitCode(0);

        $this->assertSame('photos/ADAMANCE_190224/JPG/photo test.jpg', $mapped->fresh()->object_key_original);
        $this->assertSame('/storage/photos/ADAMANCE_190224/JPG/photo test.jpg', $mapped->fresh()->legacy_url);
        $this->assertSame('/storage/photos/ADAMANCE_190224/JPG/photo test.jpg', $mapped->fresh()->legacy_thumbnail_url);
        $this->assertNull($ambiguous->fresh()->object_key_original);
    }

    public function test_reconciliation_matches_renamed_photo_folder_from_bucket_index(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();

        $image = $this->image($client, $project, [
            'legacy_id' => 'legacy-1',
            'legacy_url' => 'https://www.stimergie.fr/photos/ADAMANCE_Gamme%20Fraiche_14112024/JPG/ADAMANCE_010824_GAMME%20FRAICHE1050.jpg',
        ]);

        Storage::disk('scaleway')->put('photos/ADAMANCE_GAMME FRAICHE 141124/ADAMANCE_010824_GAMME FRAICHE1050.jpg', 'hd-content');
        Storage::disk('scaleway')->put('photos/ADAMANCE_GAMME FRAICHE 141124/JPG/ADAMANCE_010824_GAMME FRAICHE1050.jpg', 'web-content');

        $this->artisan('images:reconcile-scaleway-assets', ['--prefix' => 'photos'])
            ->assertExitCode(0);

        $image->refresh();

        $this->assertSame('photos/ADAMANCE_GAMME FRAICHE 141124/ADAMANCE_010824_GAMME FRAICHE1050.jpg', $image->object_key_original);
        $this->assertSame('photos/ADAMANCE_GAMME FRAICHE 141124/ADAMANCE_010824_GAMME FRAICHE1050.jpg', $image->object_key_hd);
        $this->assertSame('photos/ADAMANCE_GAMME FRAICHE 141124/JPG/ADAMANCE_010824_GAMME FRAICHE1050.jpg', $image->object_key_web);
        $this->assertNull($image->object_key_thumb);
        $this->assertSame('/storage/photos/ADAMANCE_GAMME FRAICHE 141124/ADAMANCE_010824_GAMME FRAICHE1050.jpg', $image->legacy_url);
        $this->assertSame('/storage/photos/ADAMANCE_GAMME FRAICHE 141124/JPG/ADAMANCE_010824_GAMME FRAICHE1050.jpg', $image->legacy_thumbnail_url);
    }

    public function test_reconciliation_rewrites_legacy_image_placeholders_without_force(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();

        $image = $this->image($client, $project, [
            'legacy_id' => '2023',
            'legacy_url' => 'https://www.stimergie.fr/photos/180°C_N°30_CHOCOLATIER LILLE/JPG/1J0A2106.jpg',
            'object_key_original' => 'legacy/images/2023/original.jpg',
        ]);

        Storage::disk('scaleway')->put('photos/180°C_N°30_CHOCOLATIER LILLE/JPG/1J0A2106.jpg', 'web-content');

        $this->artisan('images:reconcile-scaleway-assets', ['--prefix' => 'photos'])
            ->assertExitCode(0);

        $image->refresh();

        $this->assertSame('photos/180°C_N°30_CHOCOLATIER LILLE/JPG/1J0A2106.jpg', $image->object_key_original);
        $this->assertSame('photos/180°C_N°30_CHOCOLATIER LILLE/JPG/1J0A2106.jpg', $image->object_key_web);
        $this->assertNull($image->object_key_thumb);
        $this->assertSame('photos/180°C_N°30_CHOCOLATIER LILLE/JPG/1J0A2106.jpg', $image->object_key_hd);
    }

    public function test_audit_reports_images_already_reconciled_under_photos_prefix(): void
    {
        [$client, $project] = $this->clientAndProject();

        $this->image($client, $project, [
            'legacy_id' => 'legacy-1',
            'object_key_original' => 'photos/ADAMANCE_190224/original.jpg',
        ]);

        Artisan::call('images:audit-storage', ['--prefix' => 'photos']);

        $output = Artisan::output();

        $this->assertStringContainsString('Avec object_key_original dans photos/', $output);
        $this->assertStringContainsString('Images mappables vers photos/', $output);
    }

    public function test_variant_generation_uses_original_from_bucket(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $file = UploadedFile::fake()->image('source.jpg', 800, 600);

        Storage::disk('scaleway')->put('photos/client/source.jpg', file_get_contents($file->getRealPath()));

        $image = $this->image($client, $project, [
            'legacy_id' => 'legacy-1',
            'object_key_original' => 'photos/client/source.jpg',
            'status' => 'pending_upload',
        ]);

        $this->artisan('images:generate-variants', ['--limit' => 1])
            ->assertExitCode(0);

        $image->refresh();

        $this->assertSame('photos/projet-storage/web/'.$image->id.'.jpg', $image->object_key_web);
        $this->assertSame('photos/projet-storage/miniatures/'.$image->id.'.jpg', $image->object_key_thumb);
        $this->assertSame('photos/projet-storage/hd/'.$image->id.'.jpg', $image->object_key_original);
        $this->assertSame('photos/projet-storage/hd/'.$image->id.'.jpg', $image->object_key_hd);
        $this->assertSame('ready', $image->status);

        Storage::disk('scaleway')->assertExists($image->object_key_thumb);
        Storage::disk('scaleway')->assertExists($image->object_key_web);
        Storage::disk('scaleway')->assertExists($image->object_key_hd);

        $thumbSize = getimagesize(Storage::disk('scaleway')->path($image->object_key_thumb));
        $webSize = getimagesize(Storage::disk('scaleway')->path($image->object_key_web));

        $this->assertSame([640, 480], [$thumbSize[0], $thumbSize[1]]);
        $this->assertSame([800, 600], [$webSize[0], $webSize[1]]);
        $this->assertLessThan(
            Storage::disk('scaleway')->size($image->object_key_web),
            Storage::disk('scaleway')->size($image->object_key_thumb),
        );

        foreach (['original', 'thumb', 'web', 'hd'] as $kind) {
            $this->assertDatabaseHas('image_variants', [
                'image_id' => $image->id,
                'kind' => $kind,
            ]);
        }
    }

    public function test_missing_web_variant_generation_targets_only_project_images_without_usable_web(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $otherProject = Project::create([
            'client_id' => $client->id,
            'name' => 'Autre projet',
            'slug' => 'autre-projet',
            'status' => 'active',
        ]);
        $file = UploadedFile::fake()->image('source.jpg', 800, 600);
        $otherFile = UploadedFile::fake()->image('other.jpg', 800, 600);

        Storage::disk('scaleway')->put('photos/client/source.jpg', file_get_contents($file->getRealPath()));
        Storage::disk('scaleway')->put('photos/other/source.jpg', file_get_contents($otherFile->getRealPath()));

        $image = $this->image($client, $project, [
            'legacy_id' => 'legacy-1',
            'object_key_original' => 'photos/client/source.jpg',
            'object_key_web' => 'photos/client/source.jpg',
            'object_key_hd' => 'photos/client/source.jpg',
            'status' => 'pending_upload',
        ]);
        $otherImage = $this->image($client, $otherProject, [
            'legacy_id' => 'legacy-2',
            'object_key_original' => 'photos/other/source.jpg',
            'object_key_web' => null,
            'object_key_hd' => 'photos/other/source.jpg',
            'status' => 'pending_upload',
        ]);

        $this->artisan('images:generate-variants', [
            '--project' => $project->id,
            '--missing-web-only' => true,
        ])->assertExitCode(0);

        $image->refresh();
        $otherImage->refresh();

        $this->assertSame('photos/projet-storage/web/'.$image->id.'.jpg', $image->object_key_web);
        $this->assertSame('photos/projet-storage/miniatures/'.$image->id.'.jpg', $image->object_key_thumb);
        $this->assertNull($otherImage->object_key_web);

        Storage::disk('scaleway')->assertExists($image->object_key_web);
        Storage::disk('scaleway')->assertMissing('photos/autre-projet/web/'.$otherImage->id.'.jpg');
    }

    public function test_web_variant_generation_job_processes_missing_project_variants(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $file = UploadedFile::fake()->image('source.jpg', 800, 600);
        Storage::disk('scaleway')->put('photos/client/source.jpg', file_get_contents($file->getRealPath()));

        $image = $this->image($client, $project, [
            'legacy_id' => 'legacy-job-1',
            'object_key_original' => 'photos/client/source.jpg',
            'object_key_web' => null,
            'object_key_hd' => 'photos/client/source.jpg',
            'status' => 'pending_upload',
        ]);
        $job = AssetTransferJob::create([
            'started_by' => null,
            'status' => 'pending',
            'mode' => 'web-variant-generation',
            'total_folders' => 0,
            'folders' => [$project->name],
            'completed_folders' => [],
            'failed_folder_details' => [],
            'metadata' => [
                'web_variant_generation' => [
                    'project_id' => $project->id,
                    'source_prefix' => 'photos',
                    'scope_label' => $project->name,
                ],
            ],
        ]);

        (new RunMissingWebVariantGenerationJob($job->id))->handle(
            app(ImageVariantGenerator::class),
            app(ProjectImageStoragePath::class),
        );

        $image->refresh();
        $job->refresh();

        $this->assertSame('photos/projet-storage/JPG/source.jpg', $image->object_key_web);
        $this->assertSame('photos/client/source.jpg', $image->object_key_original);
        $this->assertSame('photos/client/source.jpg', $image->object_key_hd);
        $this->assertSame('completed', $job->status);
        $this->assertSame(1, $job->total_folders);
        $this->assertSame(1, $job->metadata['web_variant_generation']['totals']['generated']);
        Storage::disk('scaleway')->assertExists($image->object_key_web);
    }

    public function test_project_photo_folder_audit_reports_risks_without_modifying_objects_or_database(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/source.jpg',
            'object_key_web' => 'images/JPG/source.jpg',
            'object_key_thumb' => 'images/JPG/source.jpg',
            'object_key_hd' => 'photos/projet-storage/source.jpg',
        ]);

        Storage::disk('scaleway')->put('photos/projet-storage/source.jpg', 'original-content');
        Storage::disk('scaleway')->put('images/JPG/source.jpg', 'legacy-web-content');

        $exitCode = Artisan::call('images:audit-project-photo-folders', ['--project' => $project->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dry-run', $output);
        $this->assertStringContainsString('web_not_conforming', $output);
        $this->assertStringContainsString('thumb_not_conforming', $output);
        $this->assertStringContainsString('hd_not_conforming', $output);
        $this->assertStringContainsString('thumb_points_to_web', $output);
        $this->assertSame('images/JPG/source.jpg', $image->fresh()->object_key_web);
        Storage::disk('scaleway')->assertExists('images/JPG/source.jpg');
    }

    public function test_project_variant_migration_dry_run_does_not_copy_or_update_database(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $image = $this->legacyVariantImage($client, $project);

        $exitCode = Artisan::call('images:migrate-project-variants', ['--project' => $project->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dry-run', $output);
        Storage::disk('scaleway')->assertMissing("photos/projet-storage/web/{$image->id}.jpg");
        Storage::disk('scaleway')->assertMissing("photos/projet-storage/miniatures/{$image->id}.jpg");
        Storage::disk('scaleway')->assertMissing("photos/projet-storage/hd/{$image->id}.jpg");
        $this->assertSame('images/JPG/source.jpg', $image->fresh()->object_key_web);
        $this->assertSame('images/thumbs/source.jpg', $image->fresh()->object_key_thumb);
    }

    public function test_project_variant_migration_moves_legacy_web_and_hd_without_generating_thumbnail(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $image = $this->legacyVariantImage($client, $project);

        $this->artisan('images:migrate-project-variants', [
            '--project' => $project->id,
            '--execute' => true,
        ])->assertExitCode(0);

        $image->refresh();
        $webTarget = "photos/projet-storage/web/{$image->id}.jpg";
        $hdTarget = "photos/projet-storage/hd/{$image->id}.jpg";

        $this->assertSame($webTarget, $image->object_key_web);
        $this->assertSame('images/thumbs/source.jpg', $image->object_key_thumb);
        $this->assertSame($hdTarget, $image->object_key_original);
        $this->assertSame($hdTarget, $image->object_key_hd);
        Storage::disk('scaleway')->assertExists($webTarget);
        Storage::disk('scaleway')->assertExists($hdTarget);
        Storage::disk('scaleway')->assertExists('photos/projet-storage/web/.keep');
        Storage::disk('scaleway')->assertExists('photos/projet-storage/hd/.keep');
        Storage::disk('scaleway')->assertExists('photos/projet-storage/miniatures/.keep');
        Storage::disk('scaleway')->assertMissing('images/JPG/source.jpg');
        Storage::disk('scaleway')->assertMissing('photos/projet-storage/source.jpg');
        Storage::disk('scaleway')->assertExists('images/thumbs/source.jpg');
        $this->assertDatabaseHas('image_variants', [
            'image_id' => $image->id,
            'kind' => 'web',
            'object_key' => $webTarget,
        ]);
        $this->assertDatabaseHas('image_variants', [
            'image_id' => $image->id,
            'kind' => 'hd',
            'object_key' => $hdTarget,
        ]);
        $this->assertDatabaseHas('image_variants', [
            'image_id' => $image->id,
            'kind' => 'original',
            'object_key' => $hdTarget,
        ]);

        $this->artisan('images:migrate-project-variants', [
            '--project' => $project->id,
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('image_variants', 3);
    }

    public function test_project_thumbnail_generation_creates_thumbnail_without_touching_web_or_hd(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $file = UploadedFile::fake()->image('source.jpg', 900, 600);
        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/hd/source.jpg',
            'object_key_web' => 'photos/projet-storage/web/source.jpg',
            'object_key_thumb' => null,
            'object_key_hd' => 'photos/projet-storage/hd/source.jpg',
        ]);
        Storage::disk('scaleway')->put('photos/projet-storage/hd/source.jpg', file_get_contents($file->getRealPath()));
        Storage::disk('scaleway')->put('photos/projet-storage/web/source.jpg', 'web-content');

        $this->artisan('images:generate-project-thumbnails', [
            '--project' => $project->id,
        ])->assertExitCode(0);

        $image->refresh();
        $thumbTarget = "photos/projet-storage/miniatures/{$image->id}.jpg";

        $this->assertSame($thumbTarget, $image->object_key_thumb);
        $this->assertSame('photos/projet-storage/web/source.jpg', $image->object_key_web);
        $this->assertSame('photos/projet-storage/hd/source.jpg', $image->object_key_hd);
        Storage::disk('scaleway')->assertExists($thumbTarget);
        $thumbSize = getimagesize(Storage::disk('scaleway')->path($thumbTarget));
        $this->assertSame([640, 427], [$thumbSize[0], $thumbSize[1]]);
    }

    public function test_project_thumbnail_generation_regenerates_oversized_existing_thumbnail(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $file = UploadedFile::fake()->image('source.jpg', 900, 600);
        $thumbTarget = 'photos/projet-storage/miniatures/source.jpg';
        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/hd/source.jpg',
            'object_key_web' => 'photos/projet-storage/web/source.jpg',
            'object_key_thumb' => $thumbTarget,
            'object_key_hd' => 'photos/projet-storage/hd/source.jpg',
        ]);

        Storage::disk('scaleway')->put('photos/projet-storage/hd/source.jpg', file_get_contents($file->getRealPath()));
        Storage::disk('scaleway')->put('photos/projet-storage/web/source.jpg', 'web-content');
        Storage::disk('scaleway')->put($thumbTarget, file_get_contents($file->getRealPath()));

        $this->artisan('images:generate-project-thumbnails', [
            '--project' => $project->id,
        ])->assertExitCode(0);

        $image->refresh();
        $generatedThumbTarget = "photos/projet-storage/miniatures/{$image->id}.jpg";

        $this->assertSame($generatedThumbTarget, $image->object_key_thumb);
        $this->assertSame('photos/projet-storage/web/source.jpg', $image->object_key_web);
        Storage::disk('scaleway')->assertExists($generatedThumbTarget);
        $thumbSize = getimagesize(Storage::disk('scaleway')->path($generatedThumbTarget));
        $this->assertSame([640, 427], [$thumbSize[0], $thumbSize[1]]);
    }

    public function test_project_variant_migration_does_not_copy_legacy_web_as_thumbnail(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/source.jpg',
            'object_key_web' => 'images/JPG/source.jpg',
            'object_key_thumb' => 'images/JPG/source.jpg',
            'object_key_hd' => 'photos/projet-storage/source.jpg',
        ]);
        Storage::disk('scaleway')->put('photos/projet-storage/source.jpg', 'original-content');
        Storage::disk('scaleway')->put('images/JPG/source.jpg', 'legacy-web-content');

        $this->artisan('images:migrate-project-variants', [
            '--project' => $project->id,
            '--execute' => true,
        ])->assertExitCode(0);

        $image->refresh();

        $this->assertNull($image->object_key_thumb);
        Storage::disk('scaleway')->assertMissing("photos/projet-storage/miniatures/{$image->id}.jpg");
        Storage::disk('scaleway')->assertMissing('images/JPG/source.jpg');
    }

    public function test_project_variant_migration_blocks_target_referenced_by_another_image(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $image = $this->legacyVariantImage($client, $project);
        $conflictingTarget = "photos/projet-storage/web/{$image->id}.jpg";
        $this->image($client, $project, [
            'title' => 'Image conflit',
            'object_key_original' => 'photos/projet-storage/other.jpg',
            'object_key_web' => $conflictingTarget,
            'object_key_hd' => 'photos/projet-storage/other.jpg',
        ]);

        $exitCode = Artisan::call('images:migrate-project-variants', [
            '--project' => $project->id,
            '--execute' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('[conflict]', $output);
        $this->assertSame('images/JPG/source.jpg', $image->fresh()->object_key_web);
    }

    public function test_project_variant_migration_reports_missing_legacy_source_without_updating_database(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/source.jpg',
            'object_key_web' => 'images/JPG/missing.jpg',
            'object_key_hd' => 'photos/projet-storage/source.jpg',
        ]);
        Storage::disk('scaleway')->put('photos/projet-storage/source.jpg', 'original-content');

        $exitCode = Artisan::call('images:migrate-project-variants', [
            '--project' => $project->id,
            '--execute' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[missing]', $output);
        $this->assertSame('images/JPG/missing.jpg', $image->fresh()->object_key_web);
    }

    public function test_variant_generation_creates_only_missing_project_variant_when_web_is_already_conforming(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $file = UploadedFile::fake()->image('source.jpg', 1000, 700);
        Storage::disk('scaleway')->put('photos/projet-storage/source.jpg', file_get_contents($file->getRealPath()));

        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/source.jpg',
            'object_key_hd' => 'photos/projet-storage/source.jpg',
        ]);
        $webTarget = "photos/projet-storage/web/{$image->id}.jpg";
        Storage::disk('scaleway')->put($webTarget, 'existing-web-content');
        $image->update(['object_key_web' => $webTarget]);

        $this->artisan('images:generate-variants', ['--project' => $project->id])
            ->assertExitCode(0);

        $image->refresh();

        $this->assertSame($webTarget, $image->object_key_web);
        $this->assertSame('existing-web-content', Storage::disk('scaleway')->get($webTarget));
        $this->assertSame("photos/projet-storage/miniatures/{$image->id}.jpg", $image->object_key_thumb);
        $this->assertSame("photos/projet-storage/hd/{$image->id}.jpg", $image->object_key_original);
        $this->assertSame("photos/projet-storage/hd/{$image->id}.jpg", $image->object_key_hd);
        Storage::disk('scaleway')->assertExists($image->object_key_thumb);
        Storage::disk('scaleway')->assertExists($image->object_key_hd);
    }

    public function test_global_images_prefix_decommission_is_blocked_until_database_references_are_removed(): void
    {
        Storage::fake('scaleway');

        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/source.jpg',
            'object_key_web' => 'images/web/source.jpg',
            'object_key_hd' => 'photos/projet-storage/source.jpg',
        ]);
        Storage::disk('scaleway')->put('images/web/source.jpg', 'legacy-web-content');

        $blockedExitCode = Artisan::call('images:decommission-global-prefix', ['--execute' => true]);
        $blockedOutput = Artisan::output();

        $this->assertSame(1, $blockedExitCode);
        $this->assertStringContainsString('Suppression bloquee', $blockedOutput);
        Storage::disk('scaleway')->assertExists('images/web/source.jpg');

        $image->update(['object_key_web' => 'photos/projet-storage/web/source.jpg']);

        $this->artisan('images:decommission-global-prefix', ['--execute' => true])
            ->assertExitCode(0);

        Storage::disk('scaleway')->assertMissing('images/web/source.jpg');
    }

    public function test_image_url_resolver_never_uses_legacy_fallback(): void
    {
        $image = new Image([
            'legacy_url' => 'https://www.stimergie.fr/photos/source.jpg',
        ]);
        $resolver = app(ImageUrlResolver::class);

        $this->assertNull($resolver->displayUrl($image));
    }

    /**
     * @return array{Client, Project}
     */
    private function clientAndProject(): array
    {
        $client = Client::create([
            'name' => 'Client Storage',
            'slug' => 'client-storage',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Storage',
            'slug' => 'projet-storage',
            'status' => 'active',
        ]);

        return [$client, $project];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function image(Client $client, Project $project, array $overrides = []): Image
    {
        return Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => $overrides['title'] ?? 'Image Storage',
            'status' => $overrides['status'] ?? 'ready',
            'storage_provider' => $overrides['storage_provider'] ?? 'scaleway',
            'legacy_id' => $overrides['legacy_id'] ?? null,
            'legacy_url' => $overrides['legacy_url'] ?? null,
            'object_key_original' => $overrides['object_key_original'] ?? null,
            'object_key_web' => $overrides['object_key_web'] ?? null,
            'object_key_thumb' => $overrides['object_key_thumb'] ?? null,
            'object_key_hd' => $overrides['object_key_hd'] ?? null,
        ]);
    }

    private function legacyVariantImage(Client $client, Project $project): Image
    {
        $image = $this->image($client, $project, [
            'object_key_original' => 'photos/projet-storage/source.jpg',
            'object_key_web' => 'images/JPG/source.jpg',
            'object_key_thumb' => 'images/thumbs/source.jpg',
            'object_key_hd' => 'photos/projet-storage/source.jpg',
        ]);

        Storage::disk('scaleway')->put('photos/projet-storage/source.jpg', 'original-content');
        Storage::disk('scaleway')->put('images/JPG/source.jpg', 'legacy-web-content');
        Storage::disk('scaleway')->put('images/thumbs/source.jpg', 'legacy-thumb-content');

        return $image;
    }
}
