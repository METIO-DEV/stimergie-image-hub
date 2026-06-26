<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogPostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_and_resources_require_authentication(): void
    {
        $author = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $post = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Guide publié',
            'slug' => 'guide-publie',
            'content' => 'Contenu visible',
            'content_type' => 'blog',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get(route('blog.resources'))->assertRedirect(route('login'));
        $this->get(route('blog.index'))->assertRedirect(route('login'));
        $this->get(route('blog.show', $post->slug))->assertRedirect(route('login'));
    }

    public function test_blog_posts_are_visible_to_any_authenticated_user(): void
    {
        $author = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);

        $published = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Information agence',
            'slug' => 'information-agence',
            'content' => 'Contenu visible par tous les clients connectes',
            'content_type' => 'blog',
            'category' => 'actualites',
            'external_links' => [
                ['label' => 'Direction artistique', 'url' => 'https://docs.google.com/presentation/d/example'],
            ],
            'is_published' => true,
            'published_at' => now(),
        ]);

        BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Information agence brouillon',
            'slug' => 'information-agence-brouillon',
            'content' => 'Contenu cache',
            'content_type' => 'blog',
            'is_published' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('blog.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Blog/PublicIndex')
                ->has('posts', 1)
                ->where('posts.0.id', $published->id)
                ->where('posts.0.contentType', 'blog')
                ->where('posts.0.contentTypeLabel', 'Blog')
            );

        $this->actingAs($viewer)
            ->get(route('blog.show', $published->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('post.externalLinks.0.label', 'Direction artistique')
                ->where('post.externalLinks.0.url', 'https://docs.google.com/presentation/d/example')
                ->where('post.externalLinks.0.host', 'docs.google.com')
            );
    }

    public function test_legacy_ensemble_url_redirects_to_blog_index(): void
    {
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->get(route('blog.ensemble'))
            ->assertRedirect(route('blog.index'));
    }

    public function test_legacy_blog_content_types_remain_viewable(): void
    {
        $author = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Legacy',
            'slug' => 'client-legacy',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $viewer->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $legacyBlog = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Ancien article ensemble',
            'slug' => 'ancien-article-ensemble',
            'content' => 'Contenu blog legacy',
            'content_type' => 'ensemble',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $legacyResource = BlogPost::create([
            'client_id' => $client->id,
            'author_id' => $author->id,
            'title' => 'Ancienne ressource',
            'slug' => 'ancienne-ressource',
            'content' => 'Contenu ressource legacy',
            'content_type' => 'ressource',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('blog.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('posts', 1)
                ->where('posts.0.id', $legacyBlog->id)
                ->where('posts.0.contentType', 'blog')
            );

        $this->actingAs($viewer)
            ->get(route('blog.resources'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('posts', 1)
                ->where('posts.0.id', $legacyResource->id)
                ->where('posts.0.contentType', 'resource')
            );

        $this->actingAs($viewer)->get(route('blog.show', $legacyBlog->slug))->assertOk();
        $this->actingAs($viewer)->get(route('blog.show', $legacyResource->slug))->assertOk();
    }

    public function test_resource_posts_are_limited_to_authenticated_users_clients(): void
    {
        $author = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $viewer = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        $firstClient = Client::create([
            'name' => 'Client Alpha',
            'slug' => 'client-alpha',
            'status' => 'active',
        ]);
        $secondClient = Client::create([
            'name' => 'Client Beta',
            'slug' => 'client-beta',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $firstClient->id,
            'user_id' => $viewer->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $firstPost = BlogPost::create([
            'client_id' => $firstClient->id,
            'author_id' => $author->id,
            'title' => 'Article Alpha',
            'slug' => 'article-alpha',
            'content' => 'Contenu Alpha',
            'content_type' => 'resource',
            'featured_image_object_key' => 'https://picsum.photos/seed/test-client-resource/1200/800',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $secondPost = BlogPost::create([
            'client_id' => $secondClient->id,
            'author_id' => $author->id,
            'title' => 'Article Beta',
            'slug' => 'article-beta',
            'content' => 'Contenu Beta',
            'content_type' => 'resource',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $globalResource = BlogPost::create([
            'client_id' => null,
            'author_id' => $author->id,
            'title' => 'Ressource globale',
            'slug' => 'ressource-globale',
            'content' => 'Contenu global',
            'content_type' => 'resource',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $draft = BlogPost::create([
            'client_id' => $firstClient->id,
            'author_id' => $author->id,
            'title' => 'Article Alpha brouillon',
            'slug' => 'article-alpha-brouillon',
            'content' => 'Contenu cache',
            'content_type' => 'resource',
            'is_published' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('blog.resources'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Blog/PublicIndex')
                ->has('filters.clients', 1)
                ->where('filters.clients.0.name', 'Client Alpha')
                ->where('activeFilters.clientId', '')
                ->has('posts', 1)
                ->where('posts.0.id', $firstPost->id)
                ->where('posts.0.clientName', 'Client Alpha')
                ->where('posts.0.featuredImageUrl', 'https://picsum.photos/seed/test-client-resource/1200/800')
            );

        $this->actingAs($viewer)
            ->get(route('blog.resources', ['client_id' => $secondClient->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 0));

        $this->actingAs($viewer)->get(route('blog.show', $firstPost->slug))->assertOk();
        $this->actingAs($viewer)->get(route('blog.show', $secondPost->slug))->assertNotFound();
        $this->actingAs($viewer)->get(route('blog.show', $globalResource->slug))->assertNotFound();
        $this->actingAs($viewer)->get(route('blog.show', $draft->slug))->assertNotFound();
    }

    public function test_super_admin_can_filter_all_client_resources(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $firstClient = Client::create([
            'name' => 'Client Alpha',
            'slug' => 'client-alpha',
            'status' => 'active',
        ]);
        $secondClient = Client::create([
            'name' => 'Client Beta',
            'slug' => 'client-beta',
            'status' => 'active',
        ]);

        $firstPost = BlogPost::create([
            'client_id' => $firstClient->id,
            'author_id' => $admin->id,
            'title' => 'Article Alpha',
            'slug' => 'article-alpha',
            'content' => 'Contenu Alpha',
            'content_type' => 'resource',
            'is_published' => true,
            'published_at' => now(),
        ]);
        BlogPost::create([
            'client_id' => $secondClient->id,
            'author_id' => $admin->id,
            'title' => 'Article Beta',
            'slug' => 'article-beta',
            'content' => 'Contenu Beta',
            'content_type' => 'resource',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('blog.resources', ['client_id' => $firstClient->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Blog/PublicIndex')
                ->has('filters.clients', 2)
                ->where('activeFilters.clientId', (string) $firstClient->id)
                ->has('posts', 1)
                ->where('posts.0.id', $firstPost->id)
            );

        $this->actingAs($admin)
            ->get(route('blog.show', $firstPost->slug))
            ->assertOk();
    }

    public function test_super_admin_can_create_update_and_delete_a_blog_post(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Blog',
            'slug' => 'client-blog',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Blog',
            'slug' => 'projet-blog',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Image blog',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/projet-blog/source.jpg',
            'object_key_web' => 'photos/projet-blog/JPG/source.jpg',
        ]);

        $this->actingAs($admin)
            ->post(route('blog.store'), [
                'title' => 'Nouvelle ressource',
                'content' => 'Un contenu editorial',
                'client_id' => $client->id,
                'content_type' => 'resource',
                'category' => null,
                'featured_image_id' => $image->id,
                'external_links' => [
                    ['label' => 'Plateforme Canva', 'url' => 'https://www.canva.com/design/example'],
                    ['label' => '', 'url' => ''],
                ],
                'is_published' => true,
            ])
            ->assertRedirect(route('blog.admin.index'));

        $post = BlogPost::query()->where('slug', 'nouvelle-ressource')->firstOrFail();

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'client_id' => $client->id,
            'content_type' => 'resource',
            'featured_image_object_key' => 'photos/projet-blog/JPG/source.jpg',
            'is_published' => true,
        ]);
        $this->assertSame([
            ['label' => 'Plateforme Canva', 'url' => 'https://www.canva.com/design/example'],
        ], $post->refresh()->external_links);

        $this->actingAs($admin)
            ->patch(route('blog.update', $post), [
                'title' => 'Article Blog',
                'content' => 'Un contenu mis a jour',
                'client_id' => null,
                'content_type' => 'blog',
                'category' => 'projets',
                'featured_image_id' => null,
                'remove_featured_image' => true,
                'external_links' => [
                    ['label' => 'Slides', 'url' => 'https://docs.google.com/presentation/d/updated'],
                ],
                'is_published' => false,
            ])
            ->assertRedirect(route('blog.admin.index'));

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'client_id' => null,
            'slug' => 'article-blog',
            'content_type' => 'blog',
            'category' => 'projets',
            'featured_image_object_key' => null,
            'is_published' => false,
        ]);
        $this->assertSame([
            ['label' => 'Slides', 'url' => 'https://docs.google.com/presentation/d/updated'],
        ], $post->refresh()->external_links);

        $this->actingAs($admin)
            ->delete(route('blog.destroy', $post))
            ->assertRedirect();

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_client_manager_can_only_manage_posts_for_managed_clients(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        $managedClient = Client::create([
            'name' => 'Client Gere',
            'slug' => 'client-gere',
            'status' => 'active',
        ]);
        $otherClient = Client::create([
            'name' => 'Autre Client',
            'slug' => 'autre-client',
            'status' => 'active',
        ]);

        ClientMembership::create([
            'client_id' => $managedClient->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->post(route('blog.store'), [
                'title' => 'Article autorise',
                'content' => 'Contenu',
                'client_id' => $managedClient->id,
                'content_type' => 'resource',
                'category' => null,
                'featured_image_id' => null,
                'is_published' => false,
            ])
            ->assertRedirect(route('blog.admin.index'));

        $this->actingAs($manager)
            ->post(route('blog.store'), [
                'title' => 'Article global refuse',
                'content' => 'Contenu',
                'client_id' => null,
                'content_type' => 'resource',
                'category' => null,
                'featured_image_id' => null,
                'is_published' => false,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('blog.store'), [
                'title' => 'Info Blog refusee',
                'content' => 'Contenu',
                'client_id' => $managedClient->id,
                'content_type' => 'blog',
                'category' => 'actualites',
                'featured_image_id' => null,
                'is_published' => false,
            ])
            ->assertForbidden();

        $otherPost = BlogPost::create([
            'client_id' => $otherClient->id,
            'author_id' => $manager->id,
            'title' => 'Article autre client',
            'slug' => 'article-autre-client',
            'content' => 'Contenu',
            'content_type' => 'resource',
            'is_published' => false,
        ]);

        $this->actingAs($manager)
            ->patch(route('blog.update', $otherPost), [
                'title' => 'Tentative',
                'content' => 'Contenu',
                'client_id' => $otherClient->id,
                'content_type' => 'resource',
                'category' => null,
                'featured_image_id' => null,
                'is_published' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('blog_posts', [
            'client_id' => $managedClient->id,
            'slug' => 'article-autorise',
        ]);
    }

    public function test_client_manager_cannot_use_another_clients_featured_image(): void
    {
        $manager = User::factory()->create([
            'platform_role' => 'admin_client',
            'status' => 'active',
        ]);
        $managedClient = Client::create([
            'name' => 'Client Gere',
            'slug' => 'client-gere',
            'status' => 'active',
        ]);
        $otherClient = Client::create([
            'name' => 'Client Image',
            'slug' => 'client-image',
            'status' => 'active',
        ]);
        $otherProject = Project::create([
            'client_id' => $otherClient->id,
            'name' => 'Projet Image',
            'slug' => 'projet-image',
            'status' => 'active',
        ]);
        $otherImage = Image::create([
            'client_id' => $otherClient->id,
            'project_id' => $otherProject->id,
            'created_by' => $manager->id,
            'title' => 'Image autre client',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/projet-image/source.jpg',
        ]);

        ClientMembership::create([
            'client_id' => $managedClient->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->from(route('blog.create'))
            ->post(route('blog.store'), [
                'title' => 'Article image refusee',
                'content' => 'Contenu',
                'client_id' => $managedClient->id,
                'content_type' => 'resource',
                'category' => null,
                'featured_image_id' => $otherImage->id,
                'is_published' => false,
            ])
            ->assertRedirect(route('blog.create'))
            ->assertSessionHasErrors('featured_image_id');

        $this->assertDatabaseMissing('blog_posts', [
            'slug' => 'article-image-refusee',
        ]);
    }
}
