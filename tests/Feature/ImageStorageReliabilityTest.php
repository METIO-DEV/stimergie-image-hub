<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use App\Support\ImageUrlResolver;
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
        $this->assertSame('photos/ADAMANCE_GAMME FRAICHE 141124/JPG/ADAMANCE_010824_GAMME FRAICHE1050.jpg', $image->object_key_thumb);
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
        $this->assertSame('photos/180°C_N°30_CHOCOLATIER LILLE/JPG/1J0A2106.jpg', $image->object_key_thumb);
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

        $this->assertSame('images/JPG/'.$image->id.'.jpg', $image->object_key_web);
        $this->assertSame('images/thumbs/'.$image->id.'.jpg', $image->object_key_thumb);
        $this->assertSame('photos/client/source.jpg', $image->object_key_hd);
        $this->assertSame('ready', $image->status);

        Storage::disk('scaleway')->assertExists($image->object_key_thumb);
        Storage::disk('scaleway')->assertExists($image->object_key_web);
        Storage::disk('scaleway')->assertExists($image->object_key_hd);

        foreach (['original', 'thumb', 'web', 'hd'] as $kind) {
            $this->assertDatabaseHas('image_variants', [
                'image_id' => $image->id,
                'kind' => $kind,
            ]);
        }
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
}
