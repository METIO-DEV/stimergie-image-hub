<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Support\UserInvitationMailer;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_a_user_with_client_memberships(): void
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.user-invitation',
                Mockery::on(fn (array $data) => data_get($data, 'params.user_email') === 'alice@example.test'
                    && filled(data_get($data, 'params.reset_password_url'))),
                Mockery::type(Closure::class),
            );

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

    public function test_super_admin_is_warned_when_invitation_email_is_not_configured(): void
    {
        $this->mock(UserInvitationMailer::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn(false);
        });

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'No',
            'last_name' => 'Mail',
            'email' => 'no-mail@example.test',
            'role' => 'user',
            'status' => 'active',
            'password' => '',
            'client_ids' => [],
        ])->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('users', [
            'email' => 'no-mail@example.test',
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

    public function test_super_admin_can_delete_a_user_with_client_memberships(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'platform_role' => 'user',
            'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Client',
            'slug' => 'client',
            'status' => 'active',
        ]);

        $user->clientMemberships()->create([
            'client_id' => $client->id,
            'role' => 'viewer',
            'status' => 'active',
            'is_default' => false,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
        $this->assertDatabaseMissing('client_memberships', [
            'user_id' => $user->id,
        ]);
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }
}
