<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Stimergie',
            'email' => 'admin@stimergie.test',
            'password' => Hash::make('password'),
            'platform_role' => 'super_admin',
            'status' => 'active',
        ]);

        $clientUser = User::factory()->create([
            'name' => 'Responsable client',
            'email' => 'client@stimergie.test',
            'password' => Hash::make('password'),
            'platform_role' => 'user',
            'status' => 'active',
        ]);

        $client = Client::create([
            'name' => 'Stimergie Demo',
            'slug' => 'stimergie-demo',
            'status' => 'active',
        ]);

        $client->memberships()->create([
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
            'is_default' => false,
            'created_by' => $admin->id,
        ]);

        $client->memberships()->create([
            'user_id' => $clientUser->id,
            'role' => 'manager',
            'status' => 'active',
            'is_default' => true,
            'created_by' => $admin->id,
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Chaufferie demo',
            'slug' => 'chaufferie-demo',
            'type' => 'site',
            'status' => 'active',
        ]);

        Image::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Installation pilote',
            'orientation' => 'landscape',
            'status' => 'ready',
            'storage_provider' => 'scaleway',
        ]);
    }
}
