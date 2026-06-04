<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Image;
use App\Models\ImageTagAnalysisRun;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageTagAnalysisRunManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_queue_missing_image_tag_analysis_one_by_one(): void
    {
        Storage::fake('scaleway');
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => '["chantier","thermique","bâtiment"]',
            ]),
        ]);

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $withoutTags = $this->readyImage($client, $project, 'Sans Tags', 'photos/projet/sans-tags.jpg');
        $withTags = $this->readyImage($client, $project, 'Avec Tags', 'photos/projet/avec-tags.jpg');
        $this->syncTags($withTags, ['existant']);

        $this->actingAs($admin)
            ->postJson(route('image-tag-analysis-runs.store'), [
                'mode' => 'missing',
            ])
            ->assertCreated()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.withTags', 2)
            ->assertJsonPath('stats.withoutTags', 0)
            ->assertJsonPath('run.status', 'completed')
            ->assertJsonPath('run.totalImages', 1)
            ->assertJsonPath('run.processedImages', 1);

        $this->assertSame(
            ['bâtiment', 'chantier', 'thermique'],
            $withoutTags->tags()->orderBy('slug')->pluck('name')->all(),
        );
        $this->assertSame(
            ['existant'],
            $withTags->tags()->pluck('name')->all(),
        );
    }

    public function test_manager_can_regenerate_tags_for_one_image(): void
    {
        Storage::fake('scaleway');
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => '["solaire","réseau"]',
            ]),
        ]);

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        [$client, $project] = $this->clientAndProject();
        $image = $this->readyImage($client, $project, 'Image à régénérer', 'photos/projet/regenerate.jpg');
        $this->syncTags($image, ['ancien']);

        $this->actingAs($admin)
            ->postJson(route('image-tag-analysis-runs.store'), [
                'image_id' => $image->id,
            ])
            ->assertCreated()
            ->assertJsonPath('run.mode', 'single')
            ->assertJsonPath('run.status', 'completed');

        $image->refresh();

        $this->assertSame(
            ['réseau', 'solaire'],
            $image->tags()->orderBy('slug')->pluck('name')->all(),
        );
        $this->assertSame('ai', $image->metadata['tag_source']);
        $this->assertSame('completed', $image->metadata['ai_tag_analysis_status']);
    }

    public function test_manager_can_stop_active_analysis_run(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $run = ImageTagAnalysisRun::create([
            'started_by' => $admin->id,
            'status' => 'processing',
            'mode' => 'missing',
            'total_images' => 10,
            'processed_images' => 3,
            'started_at' => now(),
            'image_ids' => [1, 2, 3],
        ]);

        $this->actingAs($admin)
            ->postJson(route('image-tag-analysis-runs.stop', $run))
            ->assertOk()
            ->assertJsonPath('run.status', 'cancelled');

        $run->refresh();

        $this->assertSame('cancelled', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame($admin->id, $run->metadata['cancelled_by']);
    }

    /**
     * @return array{Client, Project}
     */
    private function clientAndProject(): array
    {
        $client = Client::create([
            'name' => 'Client Analyse',
            'slug' => 'client-analyse',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Analyse',
            'slug' => 'projet-analyse',
            'status' => 'active',
        ]);

        return [$client, $project];
    }

    private function readyImage(Client $client, Project $project, string $title, string $objectKey): Image
    {
        Storage::disk('scaleway')->put($objectKey, 'fake-image');

        return Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => $title,
            'mime_type' => 'image/jpeg',
            'storage_provider' => 'scaleway',
            'object_key_original' => $objectKey,
            'object_key_web' => $objectKey,
            'object_key_hd' => $objectKey,
            'status' => 'ready',
        ]);
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function syncTags(Image $image, array $tags): void
    {
        $tagIds = collect($tags)
            ->map(fn (string $tag) => Tag::firstOrCreate(
                ['slug' => str($tag)->slug()->toString()],
                ['name' => $tag],
            )->id);

        $image->tags()->sync($tagIds);
    }
}
