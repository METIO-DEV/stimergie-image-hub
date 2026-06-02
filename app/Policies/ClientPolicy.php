<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active'
            && (
                $user->isSuperAdmin()
                || ($user->isClientAdmin() && $user->hasAnyClientRole(['owner', 'manager']))
            );
    }

    public function view(User $user, Client $client): bool
    {
        return $user->isSuperAdmin() || $user->hasActiveClientMembership($client);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->status === 'active';
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isSuperAdmin()
            || $user->hasClientRole($client, ['owner', 'manager']);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Client $client): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Client $client): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageMembers(User $user, Client $client): bool
    {
        return $user->isSuperAdmin() || $user->hasClientRole($client, ['owner']);
    }
}
