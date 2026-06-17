<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\Import as ImageImport;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ScalabilitySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_filters_remain_bounded_with_paginated_image_volume(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $tag = Tag::create([
            'name' => 'Volume',
            'slug' => 'volume',
        ]);

        foreach (range(1, 3) as $clientIndex) {
            $client = Client::create([
                'name' => "Client Volume {$clientIndex}",
                'slug' => "client-volume-{$clientIndex}",
                'status' => 'active',
            ]);
            $project = Project::create([
                'client_id' => $client->id,
                'name' => "Projet Volume {$clientIndex}",
                'slug' => "projet-volume-{$clientIndex}",
                'status' => 'active',
            ]);

            foreach (range(1, 60) as $imageIndex) {
                $image = Image::create([
                    'client_id' => $client->id,
                    'project_id' => $project->id,
                    'title' => "Image volume {$clientIndex}-{$imageIndex}",
                    'description' => 'Serie de controle galerie',
                    'orientation' => $imageIndex % 2 === 0 ? 'landscape' : 'portrait',
                    'status' => 'ready',
                    'storage_provider' => 'scaleway',
                    'object_key_original' => "photos/client-volume-{$clientIndex}/source-{$imageIndex}.jpg",
                    'object_key_web' => "photos/client-volume-{$clientIndex}/JPG/source-{$imageIndex}.jpg",
                    'created_at' => Carbon::now()->subSeconds(($clientIndex * 100) + $imageIndex),
                    'updated_at' => Carbon::now()->subSeconds(($clientIndex * 100) + $imageIndex),
                ]);

                if ($imageIndex % 3 === 0) {
                    $image->tags()->attach($tag->id);
                }
            }
        }

        $queryCount = $this->countQueries(fn () => $this->actingAs($admin)
            ->get(route('gallery.index', [
                'orientation' => 'landscape',
                'tag' => 'Volume',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->where('pagination.currentPage', 1)
                ->where('pagination.perPage', 60)
                ->where('pagination.total', 30)
                ->has('images', 30)
                ->etc()));

        $this->assertLessThanOrEqual(30, $queryCount);
    }

    public function test_import_progress_aggregate_handles_near_limit_batches(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject('import-volume');
        $import = ImageImport::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'started_by' => $admin->id,
            'source' => 'folder_upload',
            'status' => 'processing',
            'total_items' => 500,
            'started_at' => now(),
        ]);

        $rows = collect(range(1, 500))
            ->map(fn (int $index) => [
                'import_id' => $import->id,
                'source_identifier' => "batch/source-{$index}.jpg",
                'original_filename' => "source-{$index}.jpg",
                'relative_path' => "batch/source-{$index}.jpg",
                'mime_type' => 'image/jpeg',
                'size_bytes' => 1024,
                'checksum' => hash('sha256', "source-{$index}"),
                'object_key_original' => "photos/import-volume/source-{$index}.jpg",
                'status' => $index <= 460 ? 'done' : ($index <= 485 ? 'duplicate' : 'failed'),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        DB::table('import_items')->insert($rows);

        $queryCount = $this->countQueries(fn () => $import->refreshProgress());

        $import->refresh();

        $this->assertSame('failed', $import->status);
        $this->assertSame(500, $import->uploaded_items);
        $this->assertSame(485, $import->processed_items);
        $this->assertSame(15, $import->failed_items);
        $this->assertSame(25, $import->duplicate_items);
        $this->assertNotNull($import->finished_at);
        $this->assertLessThanOrEqual(3, $queryCount);
    }

    public function test_download_archive_handles_light_volume_with_missing_objects(): void
    {
        Storage::fake('scaleway');

        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject('zip-volume');
        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $imageIds = [];

        foreach (range(1, 30) as $index) {
            $objectKey = "photos/zip-volume/JPG/source-{$index}.jpg";
            $image = Image::create([
                'client_id' => $client->id,
                'project_id' => $project->id,
                'title' => "Archive volume {$index}",
                'status' => 'ready',
                'storage_provider' => 'scaleway',
                'object_key_original' => "photos/zip-volume/source-{$index}.jpg",
                'object_key_web' => $objectKey,
                'object_key_hd' => "photos/zip-volume/source-{$index}.jpg",
                'size_bytes' => 2048,
            ]);
            $imageIds[] = $image->id;

            if ($index % 6 !== 0) {
                Storage::disk('scaleway')->put($objectKey, "web-content-{$index}");
            }
        }

        $this->actingAs($manager)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => $imageIds,
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $tempZip = tempnam(sys_get_temp_dir(), 'download-volume-test-');

        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;
        $zipOpened = false;

        try {
            $this->assertTrue($zip->open($tempZip));
            $zipOpened = true;
            $this->assertSame(25, $zip->numFiles);
            $this->assertSame('web-content-1', $zip->getFromIndex(0));
        } finally {
            if ($zipOpened) {
                $zip->close();
            }

            @unlink($tempZip);
        }

        $this->assertSame('ready', $job->status);
        $this->assertSame(25, $job->image_count);
        $this->assertCount(5, $job->payload['skipped_images']);
    }

    /**
     * @return array{Client, Project}
     */
    private function clientAndProject(string $slug): array
    {
        $client = Client::create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet '.str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => 'projet-'.$slug,
            'status' => 'active',
        ]);

        return [$client, $project];
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
