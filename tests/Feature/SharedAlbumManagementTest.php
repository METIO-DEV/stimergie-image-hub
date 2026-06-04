<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\SharedAlbum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedAlbumManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_shared_album_from_visible_images(): void
    {
        config([
            'services.brevo.api_key' => 'brevo-test-key',
            'services.brevo.template_mailer' => 'brevo',
            'services.brevo.templates.shared_album_invitation' => 22,
            'services.brevo.sender_email' => 'contact@stimergie.fr',
            'services.brevo.sender_name' => 'Stimergie',
        ]);
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-message']),
        ]);

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
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'brevo-test-key')
            && $request['templateId'] === 22
            && $request['params']['album_name'] === 'Album client');
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
                ->etc());

        $album->update(['expires_at' => now()->subDay()]);

        $this->get(route('shared-albums.show', $album->share_key))
            ->assertNotFound();
    }
}
