<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
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

        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image Scaleway',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-assets/source.jpg',
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
                ->where('images.0.downloadUrl', route('images.download', ['image' => $image, 'variant' => 'hd']))
                ->where('images.0.webDownloadUrl', route('images.download', ['image' => $image, 'variant' => 'web']))
                ->where('images.0.hdDownloadUrl', route('images.download', ['image' => $image, 'variant' => 'hd']))
                ->etc());
    }

    public function test_image_download_route_serves_attachment_from_scaleway_object(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Download',
            'slug' => 'client-download',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Download',
            'slug' => 'projet-download',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image Telechargeable',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-download/source.jpg',
        ]);

        Storage::disk('scaleway')->put('photos/client-download/source.jpg', 'image-content');

        $this->actingAs($admin)
            ->get(route('images.download', ['image' => $image, 'variant' => 'hd']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=image-telechargeable-hd.jpg');
    }

    public function test_image_download_route_serves_requested_web_variant(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Web Download',
            'slug' => 'client-web-download',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Web Download',
            'slug' => 'projet-web-download',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image Web',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-web-download/source.jpg',
            'object_key_web' => 'images/web/source.jpg',
            'object_key_hd' => 'images/hd/source.jpg',
        ]);

        Storage::disk('scaleway')->put('images/web/source.jpg', 'web-content');
        Storage::disk('scaleway')->put('images/hd/source.jpg', 'hd-content');

        $response = $this->actingAs($admin)
            ->get(route('images.download', ['image' => $image, 'variant' => 'web']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=image-web-web.jpg');

        $this->assertSame('web-content', $response->streamedContent());
    }

    public function test_image_download_route_applies_expired_access_periods(): void
    {
        Storage::fake('scaleway');

        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Acces Direct',
            'slug' => 'client-acces-direct',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Expire',
            'slug' => 'projet-expire',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image expiree',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-acces-direct/expiree.jpg',
            'object_key_web' => 'images/web/expiree.jpg',
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
        Storage::disk('scaleway')->put('images/web/expiree.jpg', 'web-content');

        $this->actingAs($user)
            ->get(route('images.download', ['image' => $image, 'variant' => 'web']))
            ->assertForbidden();
    }

    public function test_gallery_applies_project_access_periods_for_client_users(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Acces',
            'slug' => 'client-acces',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Temporaire',
            'slug' => 'projet-temporaire',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image temporaire',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-acces/temporaire.jpg',
            'object_key_web' => 'images/web/temporaire.jpg',
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

        $this->actingAs($user)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('images', 0));

        ProjectAccessPeriod::query()->update([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('images', 1)
                ->where('images.0.id', $image->id)
                ->etc());
    }

    public function test_projects_page_applies_project_access_periods_for_client_users(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Projets Acces',
            'slug' => 'client-projets-acces',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Visible Temporairement',
            'slug' => 'projet-visible-temporairement',
            'status' => 'active',
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

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->has('projects', 0));

        ProjectAccessPeriod::query()->update([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                ->etc());
    }
}
