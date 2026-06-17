<?php

namespace Tests\Feature;

use App\Jobs\ProcessImageImportItem;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Import as ImageImport;
use App\Models\ImportItem;
use App\Models\Project;
use App\Models\User;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectImageStoragePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageImportManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_folder_import_batch(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();

        $this->actingAs($admin)
            ->postJson(route('image-imports.store'), [
                'project_id' => $project->id,
                'total_items' => 3,
                'total_bytes' => 12000,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('projectId', $project->id)
            ->assertJsonPath('clientName', $client->name);

        $this->assertDatabaseHas('imports', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'source' => 'folder_upload',
            'status' => 'pending',
            'total_items' => 3,
        ]);
    }

    public function test_super_admin_can_create_folder_import_batch_with_new_project(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Import',
            'slug' => 'client-import',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('image-imports.store'), [
                'new_project' => [
                    'client_id' => $client->id,
                    'name' => 'Campagne Bonus',
                    'type' => 'social',
                    'source_folder' => '',
                ],
                'total_items' => 2,
                'total_bytes' => 8000,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('projectName', 'Campagne Bonus')
            ->assertJsonPath('clientName', $client->name);

        $project = Project::query()
            ->where('client_id', $client->id)
            ->where('name', 'Campagne Bonus')
            ->firstOrFail();

        $this->assertSame('campagne-bonus', $project->slug);
        $this->assertSame('client-import_campagne-bonus', $project->source_folder);

        $this->assertDatabaseHas('imports', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'source' => 'folder_upload',
            'status' => 'pending',
            'total_items' => 2,
        ]);
    }

    public function test_standard_user_cannot_create_folder_import_batch(): void
    {
        $user = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        [, $project] = $this->clientAndProject();

        $this->actingAs($user)
            ->postJson(route('image-imports.store'), [
                'project_id' => $project->id,
                'total_items' => 1,
                'total_bytes' => 1000,
            ])
            ->assertForbidden();
    }

    public function test_manager_can_upload_import_item_and_queue_processing(): void
    {
        Queue::fake();
        Storage::fake('scaleway');

        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $import = ImageImport::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'started_by' => $manager->id,
            'source' => 'folder_upload',
            'status' => 'pending',
            'total_items' => 1,
            'started_at' => now(),
        ]);

        $this->actingAs($manager)
            ->postJson(route('image-imports.items.store', $import), [
                'relative_path' => 'dossier/matcha.jpg',
                'file' => UploadedFile::fake()->image('matcha.jpg', 800, 600),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('uploadedItems', 1);

        $item = ImportItem::query()->firstOrFail();

        Storage::disk('scaleway')->assertExists($item->object_key_original);
        $this->assertStringStartsWith('photos/projet-import/', $item->object_key_original);
        $this->assertStringNotContainsString('/originals/', $item->object_key_original);
        $this->assertSame('uploaded', $item->status);
        $this->assertSame('dossier/matcha.jpg', $item->relative_path);
        Queue::assertPushed(ProcessImageImportItem::class);
    }

    public function test_import_job_creates_ready_image_and_variants(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $file = UploadedFile::fake()->image('source.jpg', 800, 600);
        $objectKey = 'photos/projet-import/source.jpg';

        Storage::disk('scaleway')->put($objectKey, file_get_contents($file->getRealPath()));

        $import = ImageImport::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'started_by' => $admin->id,
            'source' => 'folder_upload',
            'status' => 'processing',
            'total_items' => 1,
            'started_at' => now(),
        ]);
        $item = $import->items()->create([
            'source_identifier' => 'source.jpg',
            'original_filename' => 'source.jpg',
            'relative_path' => 'dossier/source.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'object_key_original' => $objectKey,
            'status' => 'uploaded',
        ]);

        (new ProcessImageImportItem($item->id))->handle(
            app(ImageVariantGenerator::class),
            app(ProjectImageStoragePath::class),
        );

        $image = Image::query()->where('title', 'Source')->firstOrFail();
        $item->refresh();
        $import->refresh();

        $this->assertSame('ready', $image->status);
        $this->assertSame($objectKey, $image->object_key_original);
        $this->assertStringStartsWith('photos/projet-import/JPG/', $image->object_key_web);
        $this->assertNull($image->object_key_thumb);
        $this->assertSame($image->object_key_original, $image->object_key_hd);
        $this->assertSame('landscape', $image->orientation);
        $this->assertSame($image->id, $item->image_id);
        $this->assertSame('done', $item->status);
        $this->assertSame('completed', $import->status);
        $this->assertSame(1, $import->processed_items);
        Storage::disk('scaleway')->assertExists($image->object_key_web);
        $this->assertDatabaseMissing('image_variants', [
            'image_id' => $image->id,
            'kind' => 'thumb',
        ]);
        Storage::disk('scaleway')->assertExists($image->object_key_hd);
    }

    public function test_import_job_marks_existing_project_checksum_as_duplicate(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $checksum = hash('sha256', 'already-imported');
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Image existante',
            'checksum' => $checksum,
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/existing/source.jpg',
        ]);
        $import = ImageImport::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'started_by' => $admin->id,
            'source' => 'folder_upload',
            'status' => 'processing',
            'total_items' => 1,
            'started_at' => now(),
        ]);
        $item = $import->items()->create([
            'source_identifier' => 'duplicate.jpg',
            'original_filename' => 'duplicate.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 16,
            'checksum' => $checksum,
            'object_key_original' => 'photos/projet-import/duplicate.jpg',
            'status' => 'uploaded',
        ]);

        (new ProcessImageImportItem($item->id))->handle(
            app(ImageVariantGenerator::class),
            app(ProjectImageStoragePath::class),
        );

        $item->refresh();
        $import->refresh();

        $this->assertSame('duplicate', $item->status);
        $this->assertSame($image->id, $item->image_id);
        $this->assertSame('completed', $import->status);
        $this->assertSame(1, $import->processed_items);
        $this->assertSame(1, $import->duplicate_items);
        $this->assertDatabaseCount('images', 1);
    }

    public function test_super_admin_can_sync_project_bucket_images_into_database(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $original = UploadedFile::fake()->image('source.jpg', 1800, 1200);
        $web = UploadedFile::fake()->image('source.jpg', 900, 600);

        Storage::disk('scaleway')->put(
            'photos/projet-import/source.jpg',
            file_get_contents($original->getRealPath()),
        );
        Storage::disk('scaleway')->put(
            'photos/projet-import/JPG/source.jpg',
            file_get_contents($web->getRealPath()),
        );

        $this->actingAs($admin)
            ->postJson(route('projects.sync-bucket-images', $project))
            ->assertOk()
            ->assertJsonPath('prefix', 'photos/projet-import')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('skipped', 0);

        $image = Image::query()->firstOrFail();

        $this->assertSame($client->id, $image->client_id);
        $this->assertSame($project->id, $image->project_id);
        $this->assertSame('Source', $image->title);
        $this->assertSame('ready', $image->status);
        $this->assertSame('photos/projet-import/source.jpg', $image->object_key_original);
        $this->assertSame('photos/projet-import/JPG/source.jpg', $image->object_key_web);
        $this->assertSame('photos/projet-import/source.jpg', $image->object_key_hd);
        $this->assertNull($image->object_key_thumb);
        $this->assertSame('landscape', $image->orientation);
        $this->assertDatabaseHas('image_variants', [
            'image_id' => $image->id,
            'kind' => 'web',
            'object_key' => 'photos/projet-import/JPG/source.jpg',
        ]);
    }

    public function test_command_can_sync_project_bucket_images_into_database(): void
    {
        Storage::fake('scaleway');

        [, $project] = $this->clientAndProject();
        $original = UploadedFile::fake()->image('command-source.jpg', 1200, 900);
        $web = UploadedFile::fake()->image('command-source.jpg', 600, 450);

        Storage::disk('scaleway')->put(
            'photos/projet-import/command-source.jpg',
            file_get_contents($original->getRealPath()),
        );
        Storage::disk('scaleway')->put(
            'photos/projet-import/JPG/command-source.jpg',
            file_get_contents($web->getRealPath()),
        );

        $this->artisan('images:sync-project-bucket-assets', ['--project' => $project->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('images', [
            'project_id' => $project->id,
            'object_key_original' => 'photos/projet-import/command-source.jpg',
            'object_key_web' => 'photos/projet-import/JPG/command-source.jpg',
            'status' => 'ready',
        ]);
    }

    public function test_command_resolves_legacy_project_folder_variants_when_syncing_bucket_images(): void
    {
        Storage::fake('scaleway');

        [, $project] = $this->clientAndProject();
        $project->update(['source_folder' => 'ADAMANCE_Gamme Fraiche_14112024']);
        $original = UploadedFile::fake()->image('fruit-source.jpg', 1200, 900);

        Storage::disk('scaleway')->put(
            'photos/ADAMANCE_GAMME FRAICHE 141124/fruit-source.jpg',
            file_get_contents($original->getRealPath()),
        );

        $this->artisan('images:sync-project-bucket-assets', ['--project' => $project->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('images', [
            'project_id' => $project->id,
            'object_key_original' => 'photos/ADAMANCE_GAMME FRAICHE 141124/fruit-source.jpg',
            'status' => 'ready',
        ]);
    }

    public function test_client_manager_can_sync_project_bucket_images_into_database(): void
    {
        Storage::fake('scaleway');

        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        $original = UploadedFile::fake()->image('manager-source.jpg', 800, 600);

        Storage::disk('scaleway')->put(
            'photos/projet-import/manager-source.jpg',
            file_get_contents($original->getRealPath()),
        );

        $this->actingAs($manager)
            ->postJson(route('projects.sync-bucket-images', $project))
            ->assertOk()
            ->assertJsonPath('created', 1);

        $this->assertDatabaseHas('images', [
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'object_key_original' => 'photos/projet-import/manager-source.jpg',
        ]);
    }

    public function test_project_bucket_sync_does_not_duplicate_existing_images(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [, $project] = $this->clientAndProject();
        $original = UploadedFile::fake()->image('source.jpg', 1200, 900);

        Storage::disk('scaleway')->put(
            'photos/projet-import/source.jpg',
            file_get_contents($original->getRealPath()),
        );

        $this->actingAs($admin)
            ->postJson(route('projects.sync-bucket-images', $project))
            ->assertOk()
            ->assertJsonPath('created', 1);

        $this->actingAs($admin)
            ->postJson(route('projects.sync-bucket-images', $project))
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertDatabaseCount('images', 1);
    }

    public function test_project_bucket_sync_keeps_same_filenames_from_distinct_folders(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [, $project] = $this->clientAndProject();
        $first = UploadedFile::fake()->image('source.jpg', 1200, 900);
        $second = UploadedFile::fake()->image('source.jpg', 900, 1200);

        Storage::disk('scaleway')->put(
            'photos/projet-import/set-a/source.jpg',
            file_get_contents($first->getRealPath()),
        );
        Storage::disk('scaleway')->put(
            'photos/projet-import/set-a/JPG/source.jpg',
            file_get_contents($first->getRealPath()),
        );
        Storage::disk('scaleway')->put(
            'photos/projet-import/set-b/source.jpg',
            file_get_contents($second->getRealPath()),
        );
        Storage::disk('scaleway')->put(
            'photos/projet-import/set-b/JPG/source.jpg',
            file_get_contents($second->getRealPath()),
        );

        $this->actingAs($admin)
            ->postJson(route('projects.sync-bucket-images', $project))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('created', 2);

        $this->assertDatabaseHas('images', [
            'project_id' => $project->id,
            'object_key_original' => 'photos/projet-import/set-a/source.jpg',
            'object_key_web' => 'photos/projet-import/set-a/JPG/source.jpg',
        ]);
        $this->assertDatabaseHas('images', [
            'project_id' => $project->id,
            'object_key_original' => 'photos/projet-import/set-b/source.jpg',
            'object_key_web' => 'photos/projet-import/set-b/JPG/source.jpg',
        ]);
    }

    public function test_project_bucket_sync_uses_exact_source_folder_as_bucket_prefix(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [, $project] = $this->clientAndProject();
        $project->update(['source_folder' => 'Client Name/SHOOT HD_01012026']);
        $original = UploadedFile::fake()->image('source.jpg', 1200, 900);

        Storage::disk('scaleway')->put(
            'photos/Client Name/SHOOT HD_01012026/source.jpg',
            file_get_contents($original->getRealPath()),
        );

        $this->actingAs($admin)
            ->postJson(route('projects.sync-bucket-images', $project))
            ->assertOk()
            ->assertJsonPath('prefix', 'photos/Client Name/SHOOT HD_01012026')
            ->assertJsonPath('created', 1);

        $this->assertDatabaseHas('images', [
            'project_id' => $project->id,
            'object_key_original' => 'photos/Client Name/SHOOT HD_01012026/source.jpg',
        ]);
    }

    public function test_standard_user_cannot_sync_project_bucket_images(): void
    {
        Storage::fake('scaleway');

        $user = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        [, $project] = $this->clientAndProject();

        $this->actingAs($user)
            ->postJson(route('projects.sync-bucket-images', $project))
            ->assertForbidden();
    }

    /**
     * @return array{Client, Project}
     */
    private function clientAndProject(): array
    {
        $client = Client::create([
            'name' => 'Entreprise Import',
            'slug' => 'entreprise-import',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Import',
            'slug' => 'projet-import',
            'status' => 'active',
        ]);

        return [$client, $project];
    }
}
