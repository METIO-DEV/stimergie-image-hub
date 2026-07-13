<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\SharedAlbum;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class SharedAlbumManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_shared_album_from_visible_images(): void
    {
        Mail::shouldReceive('send')
            ->twice()
            ->with(
                'emails.shared-album-invitation',
                Mockery::on(fn (array $data) => data_get($data, 'params.album_name') === 'Album client'
                    && filled(data_get($data, 'params.share_url'))),
                Mockery::type(Closure::class),
            );

        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Partage',
            'slug' => 'client-partage',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Partage',
            'slug' => 'projet-partage',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image partage',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-partage/source.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->post(route('shared-albums.store'), [
            'name' => 'Album client',
            'description' => 'Selection projet',
            'recipients' => 'client@example.test; autre@example.test',
            'message' => 'Voici les images.',
            'starts_at' => '2026-06-01',
            'expires_at' => '2026-06-30',
            'image_ids' => [$image->id],
        ])->assertRedirect();

        $album = SharedAlbum::firstOrFail();

        $this->assertDatabaseHas('shared_albums', [
            'id' => $album->id,
            'name' => 'Album client',
            'client_id' => $client->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('shared_album_images', [
            'shared_album_id' => $album->id,
            'image_id' => $image->id,
        ]);
        $this->assertSame(
            ['client@example.test', 'autre@example.test'],
            $album->fresh()->metadata['recipients'],
        );
    }

    public function test_shared_album_public_page_hides_expired_album(): void
    {
        $client = Client::create([
            'name' => 'Client Expiration',
            'slug' => 'client-expiration',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Expiration',
            'slug' => 'projet-expiration',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image visible',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-expiration/source.jpg',
            'object_key_web' => 'photos/client-expiration/web/source.jpg',
        ]);
        $album = SharedAlbum::create([
            'client_id' => $client->id,
            'name' => 'Album visible',
            'share_key' => 'share-visible',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $album->images()->attach($image->id, ['position' => 1]);

        $this->get(route('shared-albums.show', $album->share_key))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SharedAlbums/Show')
                ->where('album.name', 'Album visible')
                ->where('album.images.0.id', $image->id)
                ->where('album.images.0.imageUrl', fn (string $url) => str_contains($url, "/shared-albums/{$album->share_key}/images/{$image->id}/asset")
                    && str_contains($url, 'variant=display')
                    && str_contains($url, 'signature='))
                ->etc());

        $album->update(['expires_at' => now()->subDay()]);

        $this->get(route('shared-albums.show', $album->share_key))
            ->assertNotFound();
    }

    public function test_shared_album_image_asset_requires_signature_and_active_album(): void
    {
        Storage::fake('scaleway');

        $client = Client::create([
            'name' => 'Client Asset Album',
            'slug' => 'client-asset-album',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Asset Album',
            'slug' => 'projet-asset-album',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image album signee',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_web' => 'images/web/album-signee.jpg',
        ]);
        $album = SharedAlbum::create([
            'client_id' => $client->id,
            'name' => 'Album asset',
            'share_key' => 'share-asset',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $album->images()->attach($image->id, ['position' => 1]);
        Storage::disk('scaleway')->put('images/web/album-signee.jpg', 'album-signed-content');
        Storage::disk('scaleway')->assertExists('images/web/album-signee.jpg');

        $this->get(route('shared-albums.images.asset', [
            'shareKey' => $album->share_key,
            'image' => $image,
            'variant' => 'display',
        ]))->assertForbidden();

        $signedUrl = URL::temporarySignedRoute(
            'shared-albums.images.asset',
            now()->addMinutes(10),
            ['shareKey' => $album->share_key, 'image' => $image, 'variant' => 'display'],
        );

        $response = $this->get($signedUrl)->assertRedirect();

        $this->assertStringContainsString('images/web/album-signee.jpg', $response->headers->get('Location'));

        $album->update(['expires_at' => now()->subMinute()]);

        $this->get($signedUrl)->assertNotFound();
    }

    public function test_shared_album_download_contains_available_web_images(): void
    {
        Storage::fake('scaleway');

        $client = Client::create([
            'name' => 'Client Album ZIP',
            'slug' => 'client-album-zip',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Album ZIP',
            'slug' => 'projet-album-zip',
            'status' => 'active',
        ]);
        $firstImage = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Premiere image',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_web' => 'images/web/premiere.jpg',
        ]);
        $missingImage = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image manquante',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_web' => 'images/web/manquante.jpg',
        ]);
        $secondImage = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Deuxieme image',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_web' => 'images/web/deuxieme.jpg',
        ]);
        $album = SharedAlbum::create([
            'client_id' => $client->id,
            'name' => 'Album ZIP',
            'share_key' => 'share-zip',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $album->images()->attach($firstImage->id, ['position' => 1]);
        $album->images()->attach($missingImage->id, ['position' => 2]);
        $album->images()->attach($secondImage->id, ['position' => 3]);

        Storage::disk('scaleway')->put('images/web/premiere.jpg', 'premiere-content');
        Storage::disk('scaleway')->put('images/web/deuxieme.jpg', 'deuxieme-content');

        $response = $this->get(route('shared-albums.download', $album->share_key))
            ->assertOk();
        $zipPath = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($zipPath));
            $this->assertSame(2, $zip->numFiles);
            $this->assertSame('001-premiere-image.jpg', $zip->getNameIndex(0));
            $this->assertSame('premiere-content', $zip->getFromIndex(0));
            $this->assertSame('003-deuxieme-image.jpg', $zip->getNameIndex(1));
            $this->assertSame('deuxieme-content', $zip->getFromIndex(1));
        } finally {
            $zip->close();
        }
    }
}
