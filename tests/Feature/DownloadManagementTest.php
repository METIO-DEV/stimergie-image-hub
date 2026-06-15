<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            'title' => 'Matcha Latte',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => $overrides['object_key_original'] ?? null,
            'object_key_web' => $overrides['object_key_web'] ?? null,
            'object_key_hd' => $overrides['object_key_hd'] ?? null,
            'size_bytes' => $overrides['size_bytes'] ?? null,
        ]);
    }
}
