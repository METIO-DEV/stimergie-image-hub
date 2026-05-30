<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_pages_are_reachable(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        foreach ([
            'gallery.index',
            'contact.index',
            'downloads.index',
            'images.index',
            'projects.index',
            'users.index',
            'access-periods.index',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_contact_request_is_recorded_in_audit_log(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('contact.send'), [
                'subject' => 'Besoin d acces',
                'message' => 'Pouvez-vous ouvrir un nouvel acces projet ?',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'contact.requested',
        ]);
    }
}
