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

    public function test_public_blog_lists_and_shows_only_published_posts(): void
    {
        $author = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $published = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Guide publié',
            'slug' => 'guide-publie',
            'content' => 'Contenu visible',
            'content_type' => 'resource',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $draft = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Guide brouillon',
            'slug' => 'guide-brouillon',
            'content' => 'Contenu cache',
            'content_type' => 'resource',
            'is_published' => false,
        ]);

        $this->get(route('blog.resources'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Blog/PublicIndex')
                ->has('posts', 1)
                ->where('posts.0.id', $published->id)
            );

        $this->get(route('blog.show', $published->slug))->assertOk();
        $this->get(route('blog.show', $draft->slug))->assertNotFound();
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

        $this->actingAs($admin)
            ->patch(route('blog.update', $post), [
                'title' => 'Article Ensemble',
                'content' => 'Un contenu mis a jour',
                'client_id' => null,
                'content_type' => 'ensemble',
                'category' => 'projets',
                'featured_image_id' => null,
                'remove_featured_image' => true,
                'is_published' => false,
            ])
            ->assertRedirect(route('blog.admin.index'));

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'client_id' => null,
            'slug' => 'article-ensemble',
            'content_type' => 'ensemble',
            'category' => 'projets',
            'featured_image_object_key' => null,
            'is_published' => false,
        ]);

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
