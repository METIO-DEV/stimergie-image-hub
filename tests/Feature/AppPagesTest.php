<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\LegalPage;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\Tag;
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
            'dashboard',
            'gallery.index',
            'contact.index',
            'downloads.index',
            'images.index',
            'imports.index',
            'projects.index',
            'users.index',
            'access-periods.index',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_default_page_redirects_to_gallery(): void
    {
        $this->get('/')
            ->assertRedirect('/gallery');
    }

    public function test_dashboard_is_reserved_to_super_admins(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
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

    public function test_standard_client_user_does_not_receive_admin_actions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Lecture',
            'slug' => 'client-lecture',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Lecture',
            'slug' => 'projet-lecture',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image lecture',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-lecture/source.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->where('canBulkAssignImages', false)
                ->has('bulkProjects', 0)
                ->where('images.0.id', $image->id)
                ->where('images.0.canManage', false)
                ->etc());

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('canCreateProject', false)
                ->has('manageableClients', 0)
                ->where('projects.0.id', $project->id)
                ->where('projects.0.canUpdate', false)
                ->etc());

        $this->actingAs($user)
            ->get(route('images.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_client_manager_receives_manage_actions_for_owned_scope(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Manager',
            'slug' => 'client-manager',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Manager',
            'slug' => 'projet-manager',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image manager',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-manager/source.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->where('canBulkAssignImages', true)
                ->has('bulkProjects', 1)
                ->where('bulkProjects.0.id', $project->id)
                ->where('images.0.id', $image->id)
                ->where('images.0.canManage', true)
                ->etc());

        $this->actingAs($manager)
            ->get(route('images.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Images/Index')
                ->where('canManageImages', true)
                ->where('images.0.id', $image->id)
                ->where('images.0.canManage', true)
                ->has('filters.projects', 1)
                ->etc());

        $this->actingAs($manager)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('canCreateProject', true)
                ->has('manageableClients', 1)
                ->where('projects.0.id', $project->id)
                ->where('projects.0.canUpdate', true)
                ->etc());
    }

    public function test_image_management_page_paginates_and_filters_on_server(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Images',
            'slug' => 'client-images',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Images',
            'slug' => 'projet-images',
            'status' => 'active',
        ]);
        $tag = Tag::create([
            'name' => 'Campagne',
            'slug' => 'campagne',
        ]);

        foreach (range(1, 25) as $index) {
            $image = Image::create([
                'client_id' => $client->id,
                'project_id' => $project->id,
                'title' => "Image gestion {$index}",
                'status' => 'ready',
                'storage_provider' => 'scaleway',
                'object_key_original' => "photos/client-images/source-{$index}.jpg",
                'created_at' => now()->subSeconds($index),
                'updated_at' => now()->subSeconds($index),
            ]);

            if ($index === 7) {
                $image->tags()->attach($tag->id);
            }
        }

        $this->actingAs($admin)
            ->get(route('images.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Images/Index')
                ->has('images', 20)
                ->where('imagePagination.total', 25)
                ->where('imagePagination.currentPage', 1)
                ->where('imagePagination.perPage', 20)
                ->etc());

        $this->actingAs($admin)
            ->get(route('images.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Images/Index')
                ->has('images', 5)
                ->where('imagePagination.total', 25)
                ->where('imagePagination.currentPage', 2)
                ->etc());

        $this->actingAs($admin)
            ->get(route('images.index', ['tag' => 'campagne']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Images/Index')
                ->has('images', 1)
                ->where('images.0.tags.0', 'Campagne')
                ->where('imagePagination.total', 1)
                ->where('activeFilters.tag', 'campagne')
                ->etc());
    }

    public function test_access_periods_page_exposes_management_action_for_admins(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('access-periods.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AccessPeriods/Index')
                ->where('canManageAccessPeriods', true)
                ->etc());
    }

    public function test_client_manager_receives_access_period_ability_from_policy(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Periodes',
            'slug' => 'client-periodes',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.abilities.canViewAccessPeriods', true)
                ->where('auth.abilities.canManageAccessPeriods', true)
                ->etc());
    }

    public function test_legal_pages_are_publicly_reachable(): void
    {
        foreach (['legal-notice', 'about', 'terms', 'terms.legacy', 'privacy', 'privacy.legacy', 'licenses'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Legal/Show')
                    ->has('page.title')
                    ->has('page.content')
                    ->etc());
        }
    }

    public function test_legal_pages_use_legacy_editable_content(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/Show')
                ->where('page.title', 'À propos')
                ->where('canEdit', false)
                ->where('page.content', fn (string $content) => str_contains($content, 'Stimergie est une plateforme française'))
                ->etc());

        $this->get(route('licenses'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/Show')
                ->where('page.title', 'Licences')
                ->where('page.content', fn (string $content) => str_contains($content, 'Licence standard'))
                ->etc());
    }

    public function test_super_admin_can_update_legal_page_content(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $page = LegalPage::query()
            ->where('page_type', 'about')
            ->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('legal-pages.update', $page), [
                'title' => 'À propos de Stimergie',
                'content' => '<h2>Contenu modifié</h2><p>Texte administrable.</p>',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('legal_pages', [
            'id' => $page->id,
            'title' => 'À propos de Stimergie',
            'content' => '<h2>Contenu modifié</h2><p>Texte administrable.</p>',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_standard_user_cannot_update_legal_page_content(): void
    {
        $user = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        $page = LegalPage::query()
            ->where('page_type', 'about')
            ->firstOrFail();

        $this->actingAs($user)
            ->patch(route('legal-pages.update', $page), [
                'title' => 'Modification refusée',
                'content' => '<p>Refusé</p>',
            ])
            ->assertForbidden();
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

    public function test_gallery_applies_filters_on_server_before_pagination(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $targetClient = Client::create([
            'name' => '180C',
            'slug' => '180c',
            'status' => 'active',
        ]);
        $targetProject = Project::create([
            'client_id' => $targetClient->id,
            'name' => 'N30 Chocolatier Lille',
            'slug' => 'n30-chocolatier-lille',
            'status' => 'active',
        ]);
        $otherClient = Client::create([
            'name' => 'Autre client',
            'slug' => 'autre-client',
            'status' => 'active',
        ]);
        $otherProject = Project::create([
            'client_id' => $otherClient->id,
            'name' => 'Autre projet',
            'slug' => 'autre-projet',
            'status' => 'active',
        ]);
        $targetImage = Image::create([
            'client_id' => $targetClient->id,
            'project_id' => $targetProject->id,
            'title' => 'Image 180C',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/180c/source.jpg',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $targetTag = Tag::create([
            'name' => 'Façade bois',
            'slug' => 'facade-bois',
        ]);
        $targetImage->tags()->attach($targetTag->id);

        foreach (range(1, 101) as $index) {
            Image::create([
                'client_id' => $otherClient->id,
                'project_id' => $otherProject->id,
                'title' => "Image autre {$index}",
                'status' => 'ready',
                'storage_provider' => 'scaleway',
                'object_key_original' => "photos/autre/source-{$index}.jpg",
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('gallery.index', [
                'client_id' => $targetClient->id,
                'project_id' => $targetProject->id,
                'tag' => $targetTag->name,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('images', 1)
                ->where('images.0.id', $targetImage->id)
                ->where('pagination.total', 1)
                ->where('activeFilters.clientId', (string) $targetClient->id)
                ->where('activeFilters.projectId', (string) $targetProject->id)
                ->where('activeFilters.tag', $targetTag->name)
                ->etc());
    }

    public function test_client_logos_are_resolved_from_scaleway_object_keys(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Logo',
            'slug' => 'client-logo',
            'status' => 'active',
            'logo_object_key' => 'legacy/clients/client-logo/logo.png',
            'legacy_logo_url' => 'https://legacy.example/logo.png',
        ]);
        Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Logo',
            'slug' => 'projet-logo',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('projects.0.clientLogo', '/storage/legacy/clients/client-logo/logo.png')
                ->etc());

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->where('clients.0.logo', '/storage/legacy/clients/client-logo/logo.png')
                ->etc());
    }

    public function test_client_logos_fall_back_to_existing_legacy_bucket_object_by_slug(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        Client::create([
            'name' => 'Client Logo',
            'slug' => 'client-logo',
            'status' => 'active',
            'logo_object_key' => null,
            'legacy_logo_url' => 'https://legacy.example/logo.png',
        ]);

        Storage::disk('scaleway')->put('clients/42/logo-legacy-client-logo.png', 'logo');

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->where('clients.0.logo', '/storage/clients/42/logo-legacy-client-logo.png')
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
