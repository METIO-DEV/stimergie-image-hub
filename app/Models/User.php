<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['legacy_id', 'name', 'email', 'password', 'platform_role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function clientMemberships(): HasMany
    {
        return $this->hasMany(ClientMembership::class);
    }

    public function downloadJobs(): HasMany
    {
        return $this->hasMany(DownloadJob::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->platform_role === 'super_admin';
    }

    public function hasActiveClientMembership(Client $client): bool
    {
        return $this->clientMemberships()
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->exists();
    }

    public function hasClientRole(Client $client, array $roles): bool
    {
        return $this->clientMemberships()
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->whereIn('role', $roles)
            ->exists();
    }

    public function hasAnyClientRole(array $roles): bool
    {
        return $this->clientMemberships()
            ->where('status', 'active')
            ->whereIn('role', $roles)
            ->exists();
    }
}
