<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_a_user_with_client_memberships(): void
    {
        config([
            'services.brevo.api_key' => 'brevo-test-key',
            'services.brevo.templates.registration' => 11,
            'services.brevo.sender_email' => 'contact@stimergie.fr',
            'services.brevo.sender_name' => 'Stimergie',
        ]);
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'registration-message']),
        ]);

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $firstClient = Client::create([
            'name' => 'Client A',
            'slug' => 'client-a',
            'status' => 'active',
        ]);
        $secondClient = Client::create([
            'name' => 'Client B',
            'slug' => 'client-b',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Alice',
            'last_name' => 'Licata',
            'email' => 'alice@example.test',
            'role' => 'admin_client',
            'status' => 'active',
            'password' => '',
            'client_ids' => [$firstClient->id],
        ])->assertRedirect();

        $user = User::query()->where('email', 'alice@example.test')->firstOrFail();

        $this->assertDatabaseHas('client_memberships', [
            'client_id' => $firstClient->id,
            'user_id' => $user->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'platform_role' => 'admin_client',
        ]);
        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'brevo-test-key')
            && $request['templateId'] === 11
            && $request['to'][0]['email'] === 'alice@example.test'
            && isset($request['params']['reset_password_url']));

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice.martin@example.test',
            'role' => 'user',
            'status' => 'paused',
            'password' => '',
            'client_ids' => [$secondClient->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Alice Martin',
            'email' => 'alice.martin@example.test',
            'platform_role' => 'user',
            'status' => 'paused',
        ]);
        $this->assertDatabaseMissing('client_memberships', [
            'client_id' => $firstClient->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('client_memberships', [
            'client_id' => $secondClient->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'status' => 'paused',
        ]);
    }

    public function test_non_super_admin_cannot_create_global_user(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::create([
            'name' => 'Client',
            'slug' => 'client',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('users.store'), [
            'first_name' => 'Bob',
            'last_name' => 'Test',
            'email' => 'bob@example.test',
            'role' => 'user',
            'status' => 'active',
            'password' => '',
            'client_ids' => [$client->id],
        ])->assertForbidden();
    }
}
