<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\LegalPage;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\Tag;
use App\Models\User;
use App\Support\ImageRightsExtensionRequestMailer;
use App\Support\TransactionalMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
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
            'operations.index',
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
                ->where('canAddImages', false)
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
                ->where('canAddImages', true)
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

    public function test_image_management_page_shows_legacy_rights_extension_requests(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $requester = User::factory()->create([
            'name' => 'Client Demandeur',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Cession',
            'slug' => 'client-cession',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Cession',
            'slug' => 'projet-cession',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image demande historique',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-cession/source.jpg',
            'rights_ends_at' => now()->subDay(),
            'rights_extension_requested_at' => now()->subDays(2),
            'rights_extension_requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->get(route('images.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Images/Index')
                ->has('rightsExtensionRequests', 1)
                ->where('rightsExtensionRequests.0.id', "legacy-image-{$image->id}")
                ->where('rightsExtensionRequests.0.imageTitle', 'Image demande historique')
                ->where('rightsExtensionRequests.0.requestedBy', 'Client Demandeur')
                ->where('rightsExtensionRequests.0.status', ImageRightsExtensionRequest::STATUS_REQUESTED)
                ->where('rightsExtensionRequests.0.isLegacy', true)
                ->where('rightsExtensionRequests.0.updateUrl', null)
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
                    ->has('page.safeContentHtml')
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
                ->where('page.safeContentHtml', fn (string $content) => str_contains($content, 'Stimergie est une plateforme française'))
                ->etc());

        $this->get(route('licenses'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/Show')
                ->where('page.title', 'Licences')
                ->where('page.safeContentHtml', fn (string $content) => str_contains($content, 'Licence standard'))
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

    public function test_legal_page_content_is_sanitized_before_storage_and_rendering(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $legalPage = LegalPage::query()
            ->where('page_type', 'about')
            ->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('legal-pages.update', $legalPage), [
                'title' => 'À propos sécurisé',
                'content' => '<h2 onclick="alert(1)">Titre</h2><script>alert(1)</script><p><a href="javascript:alert(2)" onmouseover="alert(3)">Lien</a><a href="//evil.example">Protocol</a><a href="https://stimergie.fr" target="_blank">Stimergie</a></p><iframe src="https://example.com"></iframe>',
            ])
            ->assertRedirect();

        $storedContent = $legalPage->fresh()->content;

        $this->assertStringContainsString('<h2>Titre</h2>', $storedContent);
        $this->assertStringContainsString('href="https://stimergie.fr"', $storedContent);
        $this->assertStringContainsString('rel="noopener noreferrer"', $storedContent);
        $this->assertStringNotContainsString('script', $storedContent);
        $this->assertStringNotContainsString('javascript:', $storedContent);
        $this->assertStringNotContainsString('//evil.example', $storedContent);
        $this->assertStringNotContainsString('onmouseover', $storedContent);
        $this->assertStringNotContainsString('onclick', $storedContent);
        $this->assertStringNotContainsString('iframe', $storedContent);

        $this->get(route('about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.safeContentHtml', $storedContent)
                ->etc());
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
                ->where('images.0.thumbUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=thumb')
                    && str_contains($url, 'signature='))
                ->where('images.0.imageUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=display')
                    && str_contains($url, 'signature='))
                ->where('images.0.downloadUrl', route('images.download', ['image' => $image, 'variant' => 'hd']))
                ->where('images.0.webDownloadUrl', route('images.download', ['image' => $image, 'variant' => 'web']))
                ->where('images.0.hdDownloadUrl', route('images.download', ['image' => $image, 'variant' => 'hd']))
                ->etc());
    }

    public function test_gallery_does_not_use_original_object_as_thumbnail(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Original Lourd',
            'slug' => 'client-original-lourd',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Original Lourd',
            'slug' => 'projet-original-lourd',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image originale lourde',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/projet-original-lourd/source.jpg',
            'object_key_web' => 'photos/projet-original-lourd/source.jpg',
            'object_key_hd' => 'photos/projet-original-lourd/source.jpg',
        ]);

        $this->actingAs($admin)
            ->get(route('gallery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->where('images.0.id', $image->id)
                ->where('images.0.thumbUrl', null)
                ->where('images.0.imageUrl', fn (string $url) => str_contains($url, "/image-assets/{$image->id}")
                    && str_contains($url, 'variant=display')
                    && str_contains($url, 'signature='))
                ->etc());
    }

    public function test_image_asset_route_requires_valid_temporary_signature(): void
    {
        Storage::fake('scaleway');

        $client = Client::create([
            'name' => 'Client Asset Signe',
            'slug' => 'client-asset-signe',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Asset Signe',
            'slug' => 'projet-asset-signe',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image signee',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_web' => 'images/web/signee.jpg',
        ]);
        Storage::disk('scaleway')->put('images/web/signee.jpg', 'signed-content');
        Storage::disk('scaleway')->assertExists('images/web/signee.jpg');

        $this->get(route('images.asset', ['image' => $image, 'variant' => 'display']))
            ->assertForbidden();

        $response = $this->get(URL::temporarySignedRoute(
            'images.asset',
            now()->addMinutes(10),
            ['image' => $image, 'variant' => 'display'],
        ))->assertRedirect();

        $this->assertStringContainsString('images/web/signee.jpg', $response->headers->get('Location'));
        $this->assertStringContainsString('expiration=', $response->headers->get('Location'));

        $this->get(URL::temporarySignedRoute(
            'images.asset',
            now()->subMinute(),
            ['image' => $image, 'variant' => 'display'],
        ))->assertForbidden();
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

    public function test_gallery_can_filter_images_by_creation_date_range(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Dates',
            'slug' => 'client-dates',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Dates',
            'slug' => 'projet-dates',
            'status' => 'active',
        ]);

        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image trop ancienne',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-dates/ancienne.jpg',
            'created_at' => '2026-05-20 10:00:00',
            'updated_at' => '2026-05-20 10:00:00',
        ]);
        $matchingImage = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image dans la periode',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-dates/periode.jpg',
            'created_at' => '2026-06-10 10:00:00',
            'updated_at' => '2026-06-10 10:00:00',
        ]);
        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image trop recente',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-dates/recente.jpg',
            'created_at' => '2026-06-20 10:00:00',
            'updated_at' => '2026-06-20 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('gallery.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-15',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->where('activeFilters.dateFrom', '2026-06-01')
                ->where('activeFilters.dateTo', '2026-06-15')
                ->where('pagination.total', 1)
                ->has('images', 1)
                ->where('images.0.id', $matchingImage->id)
                ->etc());
    }

    public function test_gallery_filters_cannot_escape_client_visibility(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $visibleClient = Client::create([
            'name' => 'Client Visible',
            'slug' => 'client-visible',
            'status' => 'active',
        ]);
        $visibleProject = Project::create([
            'client_id' => $visibleClient->id,
            'name' => 'Projet Visible',
            'slug' => 'projet-visible',
            'status' => 'active',
        ]);
        $hiddenClient = Client::create([
            'name' => 'Client Cache',
            'slug' => 'client-cache',
            'status' => 'active',
        ]);
        $hiddenProject = Project::create([
            'client_id' => $hiddenClient->id,
            'name' => 'Projet Cache',
            'slug' => 'projet-cache',
            'status' => 'active',
        ]);
        $visibleImage = Image::create([
            'client_id' => $visibleClient->id,
            'project_id' => $visibleProject->id,
            'title' => 'Image visible',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-visible/source.jpg',
        ]);
        $hiddenImage = Image::create([
            'client_id' => $hiddenClient->id,
            'project_id' => $hiddenProject->id,
            'title' => 'Image cachee',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-cache/source.jpg',
        ]);
        $sharedTag = Tag::create([
            'name' => 'Selection client',
            'slug' => 'selection-client',
        ]);

        $visibleImage->tags()->attach($sharedTag->id);
        $hiddenImage->tags()->attach($sharedTag->id);
        ClientMembership::create([
            'client_id' => $visibleClient->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('gallery.index', [
                'client_id' => $hiddenClient->id,
                'tag' => $sharedTag->name,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('images', 0)
                ->where('pagination.total', 0)
                ->etc());

        $this->actingAs($user)
            ->get(route('gallery.index', ['tag' => $sharedTag->name]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('images', 1)
                ->where('images.0.id', $visibleImage->id)
                ->where('pagination.total', 1)
                ->etc());
    }

    public function test_gallery_exposes_expired_image_rights_and_extension_action(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Droits',
            'slug' => 'client-droits',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Droits',
            'slug' => 'projet-droits',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image droits expires',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-droits/source.jpg',
            'rights_starts_at' => now()->subYear()->toDateString(),
            'rights_ends_at' => now()->subDay()->toDateString(),
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
                ->has('images', 1)
                ->where('images.0.id', $image->id)
                ->where('images.0.rightsStatus', 'expired')
                ->where('images.0.rightsStatusLabel', 'Cession expirée')
                ->where('images.0.downloadUrl', null)
                ->where('images.0.webDownloadUrl', null)
                ->where('images.0.hdDownloadUrl', null)
                ->where('images.0.canRequestRightsExtension', true)
                ->where('images.0.rightsExtensionRequestUrl', route('images.rights-extension', $image))
                ->etc());
    }

    public function test_visible_user_can_request_image_rights_extension(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Extension',
            'slug' => 'client-extension',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Extension',
            'slug' => 'projet-extension',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image extension',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-extension/source.jpg',
            'rights_ends_at' => now()->addDays(10)->toDateString(),
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->mock(ImageRightsExtensionRequestMailer::class, function ($mock) use ($image): void {
            $mock
                ->shouldReceive('send')
                ->once()
                ->with(Mockery::on(fn (ImageRightsExtensionRequest $request): bool => $request->image_id === $image->id
                    && $request->status === ImageRightsExtensionRequest::STATUS_REQUESTED))
                ->andReturn(true);
        });

        $this->actingAs($user)
            ->post(route('images.rights-extension', $image))
            ->assertRedirect();

        $image->refresh();

        $this->assertNotNull($image->rights_extension_requested_at);
        $this->assertSame($user->id, $image->rights_extension_requested_by);
        $this->assertDatabaseHas('image_rights_extension_requests', [
            'image_id' => $image->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'requested_by' => $user->id,
            'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
            'rights_ends_at' => $image->rights_ends_at->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'image.rights_extension_requested',
            'subject_type' => Image::class,
            'subject_id' => $image->id,
        ]);
    }

    public function test_gallery_exposes_rights_extension_request_status(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Suivi',
            'slug' => 'client-suivi',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Suivi',
            'slug' => 'projet-suivi',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image suivi',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-suivi/source.jpg',
            'rights_ends_at' => now()->subDay()->toDateString(),
            'rights_extension_requested_at' => now(),
            'rights_extension_requested_by' => $user->id,
        ]);
        $rightsRequest = ImageRightsExtensionRequest::create([
            'image_id' => $image->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'requested_by' => $user->id,
            'status' => ImageRightsExtensionRequest::STATUS_IN_PROGRESS,
            'rights_ends_at' => $image->rights_ends_at,
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
                ->where('images.0.id', $image->id)
                ->where('images.0.rightsExtensionRequest.id', $rightsRequest->id)
                ->where('images.0.rightsExtensionRequest.status', ImageRightsExtensionRequest::STATUS_IN_PROGRESS)
                ->where('images.0.rightsExtensionRequest.statusLabel', 'En cours')
                ->where('images.0.canRequestRightsExtension', false)
                ->etc());
    }

    public function test_admin_can_track_and_update_rights_extension_requests(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $requester = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client Admin Droits',
            'slug' => 'client-admin-droits',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Admin Droits',
            'slug' => 'projet-admin-droits',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image admin droits',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-admin-droits/source.jpg',
            'rights_ends_at' => now()->subDay()->toDateString(),
        ]);
        $rightsRequest = ImageRightsExtensionRequest::create([
            'image_id' => $image->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'requested_by' => $requester->id,
            'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
            'rights_ends_at' => $image->rights_ends_at,
        ]);

        $this->actingAs($admin)
            ->get(route('images.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Images/Index')
                ->where('rightsExtensionRequests.0.id', $rightsRequest->id)
                ->where('rightsExtensionRequests.0.imageId', $image->id)
                ->where('rightsExtensionRequests.0.status', ImageRightsExtensionRequest::STATUS_REQUESTED)
                ->where('rightsExtensionRequests.0.statusLabel', 'Demandée')
                ->has('rightsExtensionRequestStatuses', 4)
                ->etc());

        $this->actingAs($admin)
            ->patch(route('image-rights-extension-requests.update', $rightsRequest), [
                'status' => ImageRightsExtensionRequest::STATUS_ACCEPTED,
            ])
            ->assertRedirect();

        $rightsRequest->refresh();

        $this->assertSame(ImageRightsExtensionRequest::STATUS_ACCEPTED, $rightsRequest->status);
        $this->assertSame($admin->id, $rightsRequest->resolved_by);
        $this->assertNotNull($rightsRequest->resolved_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'client_id' => $client->id,
            'action' => 'image.rights_extension_status_updated',
            'subject_type' => ImageRightsExtensionRequest::class,
            'subject_id' => $rightsRequest->id,
        ]);
    }

    public function test_rights_extension_request_mailer_notifies_stimergie_and_client_admins(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Admin Stimergie',
            'email' => 'admin@stimergie.test',
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $clientAdmin = User::factory()->create([
            'name' => 'Admin Client',
            'email' => 'admin-client@example.test',
            'status' => 'active',
        ]);
        $viewer = User::factory()->create([
            'email' => 'viewer@example.test',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Mail',
            'slug' => 'client-mail',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Mail',
            'slug' => 'projet-mail',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image mail',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-mail/source.jpg',
            'rights_ends_at' => now()->addDays(5)->toDateString(),
        ]);
        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $clientAdmin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);
        $rightsRequest = ImageRightsExtensionRequest::create([
            'image_id' => $image->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'requested_by' => $viewer->id,
            'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
            'rights_ends_at' => $image->rights_ends_at,
        ]);

        $this->mock(TransactionalMailer::class, function ($mock) use ($clientAdmin, $image, $superAdmin): void {
            $mock
                ->shouldReceive('send')
                ->once()
                ->with(
                    'rights_extension_request',
                    Mockery::on(function (array $to) use ($clientAdmin, $superAdmin): bool {
                        $emails = collect($to)->pluck('email')->sort()->values()->all();

                        return $emails === collect([$clientAdmin->email, $superAdmin->email])->sort()->values()->all();
                    }),
                    Mockery::on(fn (array $params): bool => $params['image_id'] === $image->id
                        && $params['client_name'] === 'Client Mail'
                        && $params['project_name'] === 'Projet Mail')
                )
                ->andReturn(true);
        });

        $mailer = new ImageRightsExtensionRequestMailer(app(TransactionalMailer::class));

        $this->assertTrue($mailer->send($rightsRequest));
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

        $response = $this->actingAs($admin)
            ->get(route('images.download', ['image' => $image, 'variant' => 'hd']))
            ->assertRedirect();

        $this->assertStringContainsString('photos/client-download/source.jpg', $response->headers->get('Location'));
        $this->assertStringContainsString('expiration=', $response->headers->get('Location'));
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
            ->assertRedirect();

        $this->assertStringContainsString('images/web/source.jpg', $response->headers->get('Location'));
        $this->assertStringContainsString('expiration=', $response->headers->get('Location'));
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

    public function test_image_download_route_cannot_reach_another_client_image(): void
    {
        Storage::fake('scaleway');

        $user = User::factory()->create(['status' => 'active']);
        $visibleClient = Client::create([
            'name' => 'Client Direct Visible',
            'slug' => 'client-direct-visible',
            'status' => 'active',
        ]);
        $hiddenClient = Client::create([
            'name' => 'Client Direct Cache',
            'slug' => 'client-direct-cache',
            'status' => 'active',
        ]);
        $hiddenProject = Project::create([
            'client_id' => $hiddenClient->id,
            'name' => 'Projet Direct Cache',
            'slug' => 'projet-direct-cache',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $hiddenClient->id,
            'project_id' => $hiddenProject->id,
            'title' => 'Image autre client',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-direct-cache/source.jpg',
            'object_key_web' => 'images/web/client-direct-cache.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $visibleClient->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);
        Storage::disk('scaleway')->put('images/web/client-direct-cache.jpg', 'web-content');

        $this->actingAs($user)
            ->get(route('images.download', ['image' => $image, 'variant' => 'web']))
            ->assertForbidden();
    }

    public function test_image_download_route_blocks_expired_rights(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Droits Download',
            'slug' => 'client-droits-download',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Droits Download',
            'slug' => 'projet-droits-download',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image droits expirés',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-droits-download/source.jpg',
            'object_key_web' => 'images/web/client-droits-download.jpg',
            'rights_ends_at' => now()->subDay()->toDateString(),
        ]);

        Storage::disk('scaleway')->put('images/web/client-droits-download.jpg', 'web-content');

        $this->actingAs($admin)
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
