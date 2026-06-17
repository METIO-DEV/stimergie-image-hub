<?php

namespace Tests\Feature;

use App\Jobs\PrepareDownloadArchive;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use App\Support\ImageUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class DownloadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_prepare_web_download_archive_from_scaleway_objects(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/matcha.jpg',
            'object_key_hd' => 'images/hd/matcha.jpg',
        ]);

        Storage::disk('scaleway')->put('images/web/matcha.jpg', 'web-content');
        Storage::disk('scaleway')->put('images/hd/matcha.jpg', 'hd-content');

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$image->id],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();

        $this->assertFalse($job->is_hd);
        $this->assertSame('ready', $job->status);
        $this->assertNotNull($job->object_key);
        Storage::disk('scaleway')->assertExists($job->object_key);

        $response = $this->actingAs($admin)
            ->get(route('downloads.show', $job))
            ->assertRedirect();

        $this->assertStringContainsString($job->object_key, $response->headers->get('Location'));
    }

    public function test_hd_download_archive_uses_hd_object_key(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/source.jpg',
            'object_key_hd' => 'images/hd/source.jpg',
        ]);

        Storage::disk('scaleway')->put('images/web/source.jpg', 'web-source');
        Storage::disk('scaleway')->put('images/hd/source.jpg', 'hd-source');

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'hd',
            'image_ids' => [$image->id],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $tempZip = tempnam(sys_get_temp_dir(), 'download-test-');

        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempZip));
        $this->assertSame('hd-source', $zip->getFromIndex(0));
        $zip->close();
        @unlink($tempZip);

        $this->assertTrue($job->is_hd);
    }

    public function test_grouped_hd_download_archive_uses_every_hd_object_key(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject('grouped-hd-downloads');
        $firstImage = $this->image($client, $project, [
            'title' => 'First HD',
            'object_key_web' => 'images/web/first.jpg',
            'object_key_hd' => 'images/hd/first.jpg',
        ]);
        $secondImage = $this->image($client, $project, [
            'title' => 'Second HD',
            'object_key_web' => 'images/web/second.jpg',
            'object_key_hd' => 'images/hd/second.jpg',
        ]);

        Storage::disk('scaleway')->put('images/web/first.jpg', 'first-web');
        Storage::disk('scaleway')->put('images/web/second.jpg', 'second-web');
        Storage::disk('scaleway')->put('images/hd/first.jpg', 'first-hd');
        Storage::disk('scaleway')->put('images/hd/second.jpg', 'second-hd');

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'hd',
            'image_ids' => [$firstImage->id, $secondImage->id],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $entries = $this->zipEntries($job);

        $this->assertSame('ready', $job->status);
        $this->assertTrue($job->is_hd);
        $this->assertSame(2, $job->image_count);
        $this->assertSame([
            '001-first-hd.jpg' => 'first-hd',
            '002-second-hd.jpg' => 'second-hd',
        ], $entries);
        $this->assertSame('completed', $job->payload['archive_progress']['status']);
        $this->assertSame(2, $job->payload['archive_progress']['processed']);
        $this->assertSame(2, $job->payload['archive_progress']['added']);
        $this->assertSame(0, $job->payload['archive_progress']['skipped']);
    }

    public function test_cropped_download_archive_applies_selected_preset(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $source = UploadedFile::fake()->image('source.jpg', 1200, 800);
        $image = $this->image($client, $project, [
            'object_key_original' => 'images/original/source.jpg',
            'object_key_hd' => 'images/original/source.jpg',
        ]);

        Storage::disk('scaleway')->put(
            'images/original/source.jpg',
            file_get_contents($source->getRealPath()),
        );

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'crop',
            'crop_preset' => 'square',
            'crop_source' => 'hd',
            'image_ids' => [$image->id],
            'crops' => [[
                'image_id' => $image->id,
                'focus_x' => 0.5,
                'focus_y' => 0.5,
                'zoom' => 1.2,
            ]],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $tempZip = tempnam(sys_get_temp_dir(), 'download-crop-test-');
        $tempImage = tempnam(sys_get_temp_dir(), 'download-crop-image-');

        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($tempZip));
            $this->assertStringContainsString('carre-1-1.jpg', $zip->getNameIndex(0));
            file_put_contents($tempImage, $zip->getFromIndex(0));
        } finally {
            $zip->close();
            @unlink($tempZip);
        }

        $size = getimagesize($tempImage);
        @unlink($tempImage);

        $this->assertSame('ready', $job->status);
        $this->assertFalse($job->is_hd);
        $this->assertSame('square', $job->payload['crop_preset']);
        $this->assertSame('hd', $job->payload['crop_source']);
        $this->assertSame(1600, $size[0]);
        $this->assertSame(1600, $size[1]);
    }

    public function test_cropped_download_can_use_web_source(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $source = UploadedFile::fake()->image('source-web.jpg', 900, 1600);
        $image = $this->image($client, $project, [
            'object_key_original' => 'images/original/source-web.jpg',
            'object_key_web' => 'images/web/source-web.jpg',
            'object_key_hd' => 'images/original/source-web.jpg',
        ]);

        Storage::disk('scaleway')->put(
            'images/web/source-web.jpg',
            file_get_contents($source->getRealPath()),
        );

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'crop',
            'crop_preset' => 'story',
            'crop_source' => 'web',
            'image_ids' => [$image->id],
            'crops' => [[
                'image_id' => $image->id,
                'focus_x' => 0.5,
                'focus_y' => 0.5,
                'zoom' => 1,
            ]],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $tempZip = tempnam(sys_get_temp_dir(), 'download-crop-web-test-');
        $tempImage = tempnam(sys_get_temp_dir(), 'download-crop-web-image-');

        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($tempZip));
            $this->assertStringContainsString('story-9-16.jpg', $zip->getNameIndex(0));
            file_put_contents($tempImage, $zip->getFromIndex(0));
        } finally {
            $zip->close();
            @unlink($tempZip);
        }

        $size = getimagesize($tempImage);
        @unlink($tempImage);

        $this->assertSame('web', $job->payload['crop_source']);
        $this->assertSame(1080, $size[0]);
        $this->assertSame(1920, $size[1]);
    }

    public function test_cropped_download_falls_back_to_hd_when_requested_web_source_is_missing(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $source = UploadedFile::fake()->image('source-hd-only.jpg', 1200, 800);
        $image = $this->image($client, $project, [
            'title' => 'HD Only',
            'object_key_original' => 'images/original/source-hd-only.jpg',
            'object_key_hd' => 'images/original/source-hd-only.jpg',
        ]);

        Storage::disk('scaleway')->put(
            'images/original/source-hd-only.jpg',
            file_get_contents($source->getRealPath()),
        );

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'crop',
            'crop_preset' => 'web_banner',
            'crop_source' => 'web',
            'image_ids' => [$image->id],
            'crops' => [[
                'image_id' => $image->id,
                'focus_x' => 0.5,
                'focus_y' => 0.5,
                'zoom' => 1,
            ]],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $tempZip = tempnam(sys_get_temp_dir(), 'download-crop-fallback-test-');
        $tempImage = tempnam(sys_get_temp_dir(), 'download-crop-fallback-image-');

        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($tempZip));
            $this->assertStringContainsString('bandeau-web-3-1.jpg', $zip->getNameIndex(0));
            file_put_contents($tempImage, $zip->getFromIndex(0));
        } finally {
            $zip->close();
            @unlink($tempZip);
        }

        $size = getimagesize($tempImage);
        @unlink($tempImage);

        $this->assertSame('ready', $job->status);
        $this->assertSame(1, $job->image_count);
        $this->assertSame('web', $job->payload['crop_source']);
        $this->assertSame('web', $job->payload['crop_source_fallbacks'][0]['from']);
        $this->assertSame('hd', $job->payload['crop_source_fallbacks'][0]['to']);
        $this->assertSame('images/original/source-hd-only.jpg', $job->payload['crop_source_fallbacks'][0]['object_key']);
        $this->assertSame([2400, 800], [$size[0], $size[1]]);
    }

    public function test_grouped_cropped_download_archive_contains_one_crop_per_selected_image(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject('grouped-crop-downloads');
        $firstSource = UploadedFile::fake()->image('first.jpg', 1200, 800);
        $secondSource = UploadedFile::fake()->image('second.jpg', 800, 1200);
        $firstImage = $this->image($client, $project, [
            'title' => 'First Crop',
            'object_key_hd' => 'images/original/first.jpg',
        ]);
        $secondImage = $this->image($client, $project, [
            'title' => 'Second Crop',
            'object_key_hd' => 'images/original/second.jpg',
        ]);

        Storage::disk('scaleway')->put(
            'images/original/first.jpg',
            file_get_contents($firstSource->getRealPath()),
        );
        Storage::disk('scaleway')->put(
            'images/original/second.jpg',
            file_get_contents($secondSource->getRealPath()),
        );

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'crop',
            'crop_preset' => 'magazine',
            'crop_source' => 'hd',
            'image_ids' => [$firstImage->id, $secondImage->id],
            'crops' => [
                [
                    'image_id' => $firstImage->id,
                    'focus_x' => 0.25,
                    'focus_y' => 0.5,
                    'zoom' => 1.1,
                ],
                [
                    'image_id' => $secondImage->id,
                    'focus_x' => 0.75,
                    'focus_y' => 0.5,
                    'zoom' => 1.4,
                ],
            ],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $tempZip = tempnam(sys_get_temp_dir(), 'download-grouped-crop-test-');
        $firstTempImage = tempnam(sys_get_temp_dir(), 'download-grouped-crop-image-');
        $secondTempImage = tempnam(sys_get_temp_dir(), 'download-grouped-crop-image-');

        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($tempZip));
            $this->assertSame(2, $zip->numFiles);
            $this->assertSame('001-first-crop-magazine-4-3.jpg', $zip->getNameIndex(0));
            $this->assertSame('002-second-crop-magazine-4-3.jpg', $zip->getNameIndex(1));
            file_put_contents($firstTempImage, $zip->getFromIndex(0));
            file_put_contents($secondTempImage, $zip->getFromIndex(1));
        } finally {
            $zip->close();
            @unlink($tempZip);
        }

        $firstSize = getimagesize($firstTempImage);
        $secondSize = getimagesize($secondTempImage);
        @unlink($firstTempImage);
        @unlink($secondTempImage);

        $this->assertSame('ready', $job->status);
        $this->assertSame(2, $job->image_count);
        $this->assertSame([1600, 1200], [$firstSize[0], $firstSize[1]]);
        $this->assertSame([1600, 1200], [$secondSize[0], $secondSize[1]]);
    }

    public function test_cropped_download_requires_crop_for_every_image(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $firstImage = $this->image($client, $project);
        $secondImage = $this->image($client, $project);

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'crop',
            'crop_preset' => 'story',
            'crop_source' => 'web',
            'image_ids' => [$firstImage->id, $secondImage->id],
            'crops' => [[
                'image_id' => $firstImage->id,
                'focus_x' => 0.5,
                'focus_y' => 0.5,
                'zoom' => 1,
            ]],
        ])->assertInvalid('crops');

        $this->assertDatabaseCount('download_jobs', 0);
    }

    public function test_download_archive_keeps_available_images_and_records_missing_sources(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject('mixed-downloads');
        $firstImage = $this->image($client, $project, [
            'object_key_web' => 'images/web/first.jpg',
        ]);
        $missingImage = $this->image($client, $project, [
            'object_key_web' => 'images/web/missing.jpg',
        ]);
        $lastImage = $this->image($client, $project, [
            'object_key_web' => 'images/web/last.jpg',
        ]);

        Storage::disk('scaleway')->put('images/web/first.jpg', 'first-content');
        Storage::disk('scaleway')->put('images/web/last.jpg', 'last-content');

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$firstImage->id, $missingImage->id, $lastImage->id],
        ])->assertRedirect(route('downloads.index'));

        $job = DownloadJob::query()->firstOrFail();
        $tempZip = tempnam(sys_get_temp_dir(), 'download-test-');

        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;
        $zipOpened = false;

        try {
            $this->assertTrue($zip->open($tempZip));
            $zipOpened = true;
            $this->assertSame(2, $zip->numFiles);
            $this->assertSame('001-matcha-latte.jpg', $zip->getNameIndex(0));
            $this->assertSame('first-content', $zip->getFromIndex(0));
            $this->assertSame('002-matcha-latte.jpg', $zip->getNameIndex(1));
            $this->assertSame('last-content', $zip->getFromIndex(1));
        } finally {
            if ($zipOpened) {
                $zip->close();
            }

            @unlink($tempZip);
        }

        $this->assertSame('ready', $job->status);
        $this->assertSame(2, $job->image_count);
        $this->assertSame([
            ['id' => $missingImage->id, 'title' => 'Matcha Latte'],
        ], $job->payload['skipped_images']);
    }

    public function test_download_archive_failure_records_progress_when_no_sources_are_available(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject('missing-downloads');
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/missing.jpg',
        ]);
        $job = DownloadJob::create([
            'user_id' => $admin->id,
            'title' => 'Archive missing',
            'status' => 'pending',
            'is_hd' => false,
            'image_count' => 1,
            'storage_provider' => 'scaleway',
            'payload' => [
                'variant' => 'web',
                'requested_image_ids' => [$image->id],
            ],
        ]);

        try {
            (new PrepareDownloadArchive($job->id))->handle(app(ImageUrlResolver::class));
            $this->fail('The download archive job should fail when every source is missing.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Aucune image telechargeable trouvee.', $exception->getMessage());
        }

        $job->refresh();

        $this->assertSame('failed', $job->status);
        $this->assertSame('Aucune image telechargeable trouvee.', $job->error_details);
        $this->assertSame('failed', $job->payload['archive_progress']['status']);
        $this->assertSame(1, $job->payload['archive_progress']['processed']);
        $this->assertSame(0, $job->payload['archive_progress']['added']);
        $this->assertSame(1, $job->payload['archive_progress']['skipped']);
        $this->assertSame([
            ['id' => $image->id, 'title' => 'Matcha Latte'],
        ], $job->payload['skipped_images']);
    }

    public function test_user_cannot_prepare_download_for_inaccessible_client_image(): void
    {
        Storage::fake('scaleway');

        $user = User::factory()->create(['status' => 'active']);
        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/locked.jpg',
        ]);

        Storage::disk('scaleway')->put('images/web/locked.jpg', 'locked');

        $this->actingAs($user)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$image->id],
        ])->assertForbidden();
    }

    public function test_client_member_cannot_prepare_mixed_download_with_inaccessible_image(): void
    {
        Storage::fake('scaleway');

        $user = User::factory()->create(['status' => 'active']);
        [$visibleClient, $visibleProject] = $this->clientAndProject('visible-downloads');
        [$hiddenClient, $hiddenProject] = $this->clientAndProject('hidden-downloads');
        $visibleImage = $this->image($visibleClient, $visibleProject, [
            'object_key_web' => 'images/web/visible.jpg',
        ]);
        $hiddenImage = $this->image($hiddenClient, $hiddenProject, [
            'object_key_web' => 'images/web/hidden.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $visibleClient->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);
        Storage::disk('scaleway')->put('images/web/visible.jpg', 'visible');
        Storage::disk('scaleway')->put('images/web/hidden.jpg', 'hidden');

        $this->actingAs($user)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$visibleImage->id, $hiddenImage->id],
        ])->assertForbidden();

        $this->assertDatabaseCount('download_jobs', 0);
    }

    public function test_download_archive_rejects_image_with_expired_rights(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject('expired-rights-downloads');
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/expired-rights.jpg',
            'rights_ends_at' => now()->subDay()->toDateString(),
        ]);

        Storage::disk('scaleway')->put('images/web/expired-rights.jpg', 'expired-rights');

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$image->id],
        ])->assertForbidden();

        $this->assertDatabaseCount('download_jobs', 0);
    }

    public function test_client_member_can_prepare_download_for_accessible_client_image(): void
    {
        Storage::fake('scaleway');

        $user = User::factory()->create(['status' => 'active']);
        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/member.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);
        Storage::disk('scaleway')->put('images/web/member.jpg', 'member');

        $this->actingAs($user)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$image->id],
        ])->assertRedirect(route('downloads.index'));

        $this->assertDatabaseHas('download_jobs', [
            'user_id' => $user->id,
            'status' => 'ready',
            'image_count' => 1,
        ]);
    }

    public function test_client_member_cannot_prepare_download_for_expired_project_access(): void
    {
        Storage::fake('scaleway');

        $user = User::factory()->create(['status' => 'active']);
        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/expired-member.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);
        ProjectAccessPeriod::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);
        Storage::disk('scaleway')->put('images/web/expired-member.jpg', 'expired');

        $this->actingAs($user)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$image->id],
        ])->assertForbidden();

        $this->assertDatabaseCount('download_jobs', 0);
    }

    public function test_hd_download_archive_is_limited_to_fifty_images(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $imageIds = collect(range(1, 51))
            ->map(fn (int $index) => $this->image($client, $project, [
                'object_key_hd' => "images/hd/source-{$index}.jpg",
            ])->id)
            ->all();

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'hd',
            'image_ids' => $imageIds,
        ])->assertInvalid('image_ids');

        $this->assertDatabaseCount('download_jobs', 0);
    }

    public function test_download_archive_rejects_oversized_estimated_payload(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $image = $this->image($client, $project, [
            'object_key_web' => 'images/web/huge.jpg',
            'size_bytes' => 1_600_000_000,
        ]);

        $this->actingAs($admin)->post(route('downloads.store'), [
            'variant' => 'web',
            'image_ids' => [$image->id],
        ])->assertInvalid('image_ids');

        $this->assertDatabaseCount('download_jobs', 0);
    }

    public function test_expired_download_archive_cannot_be_downloaded_and_can_be_cleaned_up(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $job = DownloadJob::create([
            'user_id' => $admin->id,
            'title' => 'Archive expiree',
            'status' => 'ready',
            'is_hd' => false,
            'image_count' => 1,
            'storage_provider' => 'scaleway',
            'object_key' => 'downloads/1/archive-expiree.zip',
            'download_url' => '/storage/downloads/1/archive-expiree.zip',
            'download_url_expires_at' => now()->subMinute(),
            'processed_at' => now()->subDays(8),
        ]);
        Storage::disk('scaleway')->put($job->object_key, 'zip-content');

        $this->actingAs($admin)
            ->get(route('downloads.show', $job))
            ->assertNotFound();

        $this->artisan('downloads:cleanup-expired')
            ->expectsOutput('Archives expirees nettoyees: 1')
            ->assertExitCode(0);

        Storage::disk('scaleway')->assertMissing('downloads/1/archive-expiree.zip');
        $this->assertDatabaseHas('download_jobs', [
            'id' => $job->id,
            'status' => 'expired',
            'object_key' => null,
            'download_url' => null,
        ]);
    }

    public function test_failed_download_archive_can_be_retried_from_command(): void
    {
        Storage::fake('scaleway');
        Queue::fake();

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $job = DownloadJob::create([
            'user_id' => $admin->id,
            'title' => 'Archive echouee',
            'status' => 'failed',
            'is_hd' => true,
            'image_count' => 1,
            'storage_provider' => 'scaleway',
            'object_key' => 'downloads/1/archive-echouee.zip',
            'download_url' => '/storage/downloads/1/archive-echouee.zip',
            'download_url_expires_at' => now()->addDay(),
            'processed_at' => now()->subMinute(),
            'error_details' => 'Timeout',
            'payload' => [
                'variant' => 'hd',
                'requested_image_ids' => [123],
            ],
        ]);
        Storage::disk('scaleway')->put($job->object_key, 'partial-zip');

        $this->artisan('downloads:retry-failed', ['--id' => [$job->id]])
            ->expectsOutput('Archives relancees: 1')
            ->assertExitCode(0);

        $job->refresh();

        $this->assertSame('pending', $job->status);
        $this->assertNull($job->object_key);
        $this->assertNull($job->download_url);
        $this->assertNull($job->error_details);
        $this->assertSame('pending', $job->payload['archive_progress']['status']);
        Storage::disk('scaleway')->assertMissing('downloads/1/archive-echouee.zip');
        Queue::assertPushed(PrepareDownloadArchive::class);
    }

    public function test_legacy_stimergie_download_url_is_not_redirected(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $job = DownloadJob::create([
            'user_id' => $admin->id,
            'title' => 'Archive legacy',
            'status' => 'ready',
            'is_hd' => true,
            'image_count' => 1,
            'download_url' => 'https://www.stimergie.fr/zip-downloads/archive.zip',
            'download_url_expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('downloads.show', $job))
            ->assertNotFound();
    }

    public function test_legacy_download_urls_are_rewritten_to_scaleway_bucket(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $job = DownloadJob::create([
            'user_id' => $admin->id,
            'title' => 'Archive legacy',
            'status' => 'ready',
            'is_hd' => true,
            'image_count' => 1,
            'download_url' => 'https://www.stimergie.fr/zip-downloads/archive.zip',
            'download_url_expires_at' => now()->addDay(),
        ]);

        Storage::disk('scaleway')->put('zip-downloads/archive.zip', 'zip-content');

        $this->artisan('downloads:reconcile-legacy-urls')
            ->assertExitCode(0);

        $job->refresh();

        $this->assertSame('scaleway', $job->storage_provider);
        $this->assertSame('zip-downloads/archive.zip', $job->object_key);
        $this->assertSame('/storage/zip-downloads/archive.zip', $job->download_url);
    }

    /**
     * @return array{Client, Project}
     */
    private function clientAndProject(string $slug = 'client-downloads'): array
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function image(Client $client, Project $project, array $overrides = []): Image
    {
        return Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => $overrides['title'] ?? 'Matcha Latte',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => $overrides['object_key_original'] ?? null,
            'object_key_web' => $overrides['object_key_web'] ?? null,
            'object_key_hd' => $overrides['object_key_hd'] ?? null,
            'rights_starts_at' => $overrides['rights_starts_at'] ?? null,
            'rights_ends_at' => $overrides['rights_ends_at'] ?? null,
            'size_bytes' => $overrides['size_bytes'] ?? null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function zipEntries(DownloadJob $job): array
    {
        $tempZip = tempnam(sys_get_temp_dir(), 'download-entries-test-');
        file_put_contents($tempZip, Storage::disk('scaleway')->get($job->object_key));

        $zip = new ZipArchive;
        $entries = [];

        try {
            $this->assertTrue($zip->open($tempZip));

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entries[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
            }
        } finally {
            $zip->close();
            @unlink($tempZip);
        }

        return $entries;
    }
}
