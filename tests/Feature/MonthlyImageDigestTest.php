<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonthlyImageDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_digest_sends_brevo_template_for_accessible_new_images(): void
    {
        config([
            'services.brevo.api_key' => 'brevo-test-key',
            'services.brevo.templates.monthly_image_digest' => 33,
            'services.brevo.sender_email' => 'contact@stimergie.fr',
            'services.brevo.sender_name' => 'Stimergie',
        ]);
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'digest-message']),
        ]);

        $user = User::factory()->create([
            'email' => 'viewer@example.test',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client Digest',
            'slug' => 'client-digest',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Digest',
            'slug' => 'projet-digest',
            'status' => 'active',
        ]);
        ClientMembership::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);
        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Nouvelle image',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/projet-digest/source.jpg',
            'created_at' => '2026-06-03 10:00:00',
            'updated_at' => '2026-06-03 10:00:00',
        ]);

        $this->artisan('images:send-monthly-digest', ['--since' => '2026-06-01'])
            ->expectsOutput('Digests mensuels envoyes: 1; utilisateurs sans nouveautes: 0')
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'brevo-test-key')
            && $request['templateId'] === 33
            && $request['to'][0]['email'] === 'viewer@example.test'
            && $request['params']['image_count'] === 1
            && $request['params']['projects'][0]['project_name'] === 'Projet Digest');
    }
}
