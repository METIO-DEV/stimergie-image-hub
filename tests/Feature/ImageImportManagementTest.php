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
