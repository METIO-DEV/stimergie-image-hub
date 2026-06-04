<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use App\Support\BrevoTemplateMailer;
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
            'services.brevo.template_mailer' => 'brevo',
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
        foreach (range(1, 4) as $index) {
            Image::create([
                'client_id' => $client->id,
                'project_id' => $project->id,
                'title' => "Nouvelle image {$index}",
                'status' => 'ready',
                'storage_provider' => 'scaleway',
                'object_key_original' => "photos/projet-digest/source-{$index}.jpg",
                'created_at' => '2026-06-03 10:00:00',
                'updated_at' => '2026-06-03 10:00:00',
            ]);
        }

        $this->artisan('images:send-monthly-digest', ['--since' => '2026-06-01'])
            ->expectsOutput('Digests mensuels envoyes: 1; utilisateurs sans nouveautes: 0')
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'brevo-test-key')
            && $request['templateId'] === 33
            && $request['to'][0]['email'] === 'viewer@example.test'
            && $request['params']['image_count'] === 4
            && $request['params']['projects'][0]['project_name'] === 'Projet Digest'
            && count($request['params']['projects'][0]['preview_images']) === 3);
    }

    public function test_monthly_digest_test_command_sends_to_active_client_users(): void
    {
        $client = Client::create([
            'name' => 'Client Test Digest',
            'slug' => 'client-test-digest',
            'status' => 'active',
        ]);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Projet Test Digest',
            'slug' => 'projet-test-digest',
            'status' => 'active',
        ]);
        $firstUser = User::factory()->create([
            'email' => 'first@example.test',
            'status' => 'active',
        ]);
        $secondUser = User::factory()->create([
            'email' => 'second@example.test',
            'status' => 'active',
        ]);
        $inactiveUser = User::factory()->create([
            'email' => 'inactive@example.test',
            'status' => 'inactive',
        ]);

        foreach ([$firstUser, $secondUser, $inactiveUser] as $user) {
            ClientMembership::create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'role' => 'viewer',
                'status' => 'active',
            ]);
        }

        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Image client',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
            'object_key_original' => 'photos/client-test-digest/source.jpg',
            'created_at' => '2026-04-03 10:00:00',
            'updated_at' => '2026-04-03 10:00:00',
        ]);

        $this->mock(BrevoTemplateMailer::class, function ($mock): void {
            $mock->shouldReceive('send')
                ->twice()
                ->withArgs(fn (string $template, array $to, array $params) => $template === 'monthly_image_digest'
                    && in_array($to[0]['email'], ['first@example.test', 'second@example.test'], true)
                    && $params['projects'][0]['project_name'] === 'Projet Test Digest')
                ->andReturn(true);
        });

        $this->artisan('images:test-monthly-digest', [
            'client' => 'client-test-digest',
            '--since' => '2026-06-01',
            '--latest' => true,
        ])
            ->expectsOutput('Mail mensuel de test envoye a first@example.test.')
            ->expectsOutput('Mail mensuel de test envoye a second@example.test.')
            ->expectsOutput('Mails mensuels de test envoyes pour Client Test Digest: 2; utilisateurs sans nouveautes: 0')
            ->assertExitCode(0);
    }
}
