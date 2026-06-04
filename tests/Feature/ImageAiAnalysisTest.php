<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageAiAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_tag_analysis_requires_openai_configuration(): void
    {
        config(['services.openai.api_key' => null]);

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('images.analyze-tags'), [
                'file' => UploadedFile::fake()->image('source.jpg', 800, 600),
            ], ['Accept' => 'application/json'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'OPENAI_API_KEY non configurée.');
    }

    public function test_manager_can_generate_french_tags_for_uploaded_image(): void
    {
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => '{"tags":["Chantier","énergie solaire","CHANTIER","#Bâtiment","thermique"]}',
            ]),
        ]);

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('images.analyze-tags'), [
                'file' => UploadedFile::fake()->image('source.jpg', 800, 600),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('tags', [
                'chantier',
                'énergie solaire',
                'bâtiment',
                'thermique',
            ]);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request->url() === 'https://api.openai.com/v1/responses');
    }
}
