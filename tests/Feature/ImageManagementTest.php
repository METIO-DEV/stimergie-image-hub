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
            'tags' => 'matcha, boisson',
            'file' => UploadedFile::fake()->image('matcha.jpg', 800, 600),
        ])->assertRedirect();

        $image = Image::query()->where('title', 'Matcha Latte')->firstOrFail();

        Storage::disk('scaleway')->assertExists($image->object_key_original);
        Storage::disk('scaleway')->assertExists($image->object_key_web);
        Storage::disk('scaleway')->assertExists($image->object_key_thumb);
        Storage::disk('scaleway')->assertExists($image->object_key_hd);
        $this->assertNotSame($image->object_key_original, $image->object_key_web);
        $this->assertNotSame($image->object_key_web, $image->object_key_thumb);
        $this->assertSame('scaleway', $image->storage_provider);
        $this->assertSame('landscape', $image->orientation);
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
            'tags' => 'matcha, archive',
            'file' => UploadedFile::fake()->image('matcha-hd.jpg', 600, 900),
        ])->assertRedirect();

        $image->refresh();

        $this->assertSame('Matcha Latte HD', $image->title);
        $this->assertSame('portrait', $image->orientation);
        $this->assertSame('archived', $image->status);
        $this->assertSame('scaleway', $image->storage_provider);
        Storage::disk('scaleway')->assertExists($image->object_key_original);
        $this->assertSame(['archive', 'matcha'], $image->tags()->orderBy('slug')->pluck('slug')->all());
    }
}
