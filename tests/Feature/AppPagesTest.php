<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_pages_are_reachable(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        foreach ([
            'gallery.index',
            'contact.index',
            'downloads.index',
            'images.index',
            'projects.index',
            'users.index',
            'access-periods.index',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_contact_request_is_recorded_in_audit_log(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('contact.send'), [
                'subject' => 'Besoin d acces',
                'message' => 'Pouvez-vous ouvrir un nouvel acces projet ?',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'contact.requested',
        ]);
    }

    public function test_gallery_resolves_image_urls_from_scaleway_object_keys(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Assets',
            'slug' => 'client-assets',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Assets',
            'slug' => 'projet-assets',
            'status' => 'active',
        ]);

        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image Scaleway',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'images/originals/source.jpg',
            'object_key_web' => 'images/web/source.jpg',
            'object_key_thumb' => 'images/thumbs/source.jpg',
            'object_key_hd' => 'images/hd/source.jpg',
            'legacy_url' => 'https://legacy.example/source.jpg',
            'legacy_thumbnail_url' => 'https://legacy.example/source-thumb.jpg',
        ]);

        $this->actingAs($admin)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->where('images.0.thumbUrl', '/storage/images/thumbs/source.jpg')
                ->where('images.0.imageUrl', '/storage/images/web/source.jpg')
                ->where('images.0.downloadUrl', '/storage/images/hd/source.jpg')
                ->etc());
    }
}
