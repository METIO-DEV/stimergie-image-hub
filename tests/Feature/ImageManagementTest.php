<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_an_image_with_tags(): void
    {
        Storage::fake('scaleway');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Image',
            'slug' => 'client-image',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Image',
            'slug' => 'projet-image',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('images.store'), [
            'project_id' => $project->id,
            'title' => 'Matcha Latte',
            'description' => 'Image de test',
            'orientation' => '',
            'status' => 'ready',
            'rights_starts_at' => '2026-01-01',
            'rights_ends_at' => '2026-12-31',
            'tags' => 'matcha, boisson',
            'tag_source' => 'ai',
            'file' => UploadedFile::fake()->image('matcha.jpg', 800, 600),
        ])->assertRedirect();

        $image = Image::query()->where('title', 'Matcha Latte')->firstOrFail();

        Storage::disk('scaleway')->assertExists($image->object_key_original);
        Storage::disk('scaleway')->assertExists($image->object_key_thumb);
        Storage::disk('scaleway')->assertExists($image->object_key_web);
        Storage::disk('scaleway')->assertExists($image->object_key_hd);
        $this->assertStringStartsWith('photos/projet-image/', $image->object_key_original);
        $this->assertStringStartsWith('photos/projet-image/thumbs/', $image->object_key_thumb);
        $this->assertStringStartsWith('photos/projet-image/JPG/', $image->object_key_web);
        $this->assertSame($image->object_key_original, $image->object_key_hd);
        $this->assertNotSame($image->object_key_original, $image->object_key_web);
        $this->assertNotSame($image->object_key_original, $image->object_key_thumb);
        $this->assertSame('scaleway', $image->storage_provider);
        $this->assertSame('landscape', $image->orientation);
        $this->assertSame('2026-01-01', $image->rights_starts_at->toDateString());
        $this->assertSame('2026-12-31', $image->rights_ends_at->toDateString());
        $this->assertSame('ai', $image->metadata['tag_source']);
        $this->assertNotNull($image->metadata['ai_tags_applied_at']);
        $this->assertDatabaseHas('image_variants', [
            'image_id' => $image->id,
            'kind' => 'web',
            'object_key' => $image->object_key_web,
        ]);
        $this->assertDatabaseHas('image_variants', [
            'image_id' => $image->id,
            'kind' => 'thumb',
            'object_key' => $image->object_key_thumb,
        ]);
        $this->assertDatabaseHas('image_variants', [
            'image_id' => $image->id,
            'kind' => 'hd',
            'object_key' => $image->object_key_hd,
        ]);
        $this->assertDatabaseHas('tags', ['slug' => 'matcha']);
        $this->assertDatabaseHas('tags', ['slug' => 'boisson']);

        $this->actingAs($admin)->post(route('images.update', $image), [
            'project_id' => $project->id,
            'title' => 'Matcha Latte HD',
            'description' => 'Image modifiee',
            'orientation' => 'portrait',
            'status' => 'archived',
            'rights_starts_at' => '2026-02-01',
            'rights_ends_at' => '2026-11-30',
            'tags' => 'matcha, archive',
            'file' => UploadedFile::fake()->image('matcha-hd.jpg', 600, 900),
        ])->assertRedirect();

        $image->refresh();

        $this->assertSame('Matcha Latte HD', $image->title);
        $this->assertSame('portrait', $image->orientation);
        $this->assertSame('archived', $image->status);
        $this->assertSame('2026-02-01', $image->rights_starts_at->toDateString());
        $this->assertSame('2026-11-30', $image->rights_ends_at->toDateString());
        $this->assertSame('scaleway', $image->storage_provider);
        Storage::disk('scaleway')->assertExists($image->object_key_original);
        $this->assertStringStartsWith('photos/projet-image/', $image->object_key_original);
        $this->assertSame(['archive', 'matcha'], $image->tags()->orderBy('slug')->pluck('slug')->all());
    }

    public function test_super_admin_can_update_image_tags_without_replacing_file(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Tags',
            'slug' => 'client-tags',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Tags',
            'slug' => 'projet-tags',
            'status' => 'active',
        ]);
        $image = Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Image a taguer',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/projet-tags/source.jpg',
            'object_key_web' => 'photos/projet-tags/JPG/source.jpg',
            'object_key_hd' => 'photos/projet-tags/source.jpg',
        ]);

        $this->actingAs($admin)->post(route('images.update', $image), [
            'project_id' => $project->id,
            'title' => $image->title,
            'description' => '',
            'orientation' => '',
            'status' => 'ready',
            'rights_starts_at' => '',
            'rights_ends_at' => '',
            'tags' => 'manuel, publication, client',
            'tag_source' => 'manual',
        ])->assertRedirect();

        $image->refresh();

        $this->assertSame('Image a taguer', $image->title);
        $this->assertSame('manual', $image->metadata['tag_source']);
        $this->assertSame([
            'client',
            'manuel',
            'publication',
        ], $image->tags()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame('photos/projet-tags/source.jpg', $image->object_key_original);
    }
}
