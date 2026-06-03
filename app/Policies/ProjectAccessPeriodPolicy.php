<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;

class ProjectAccessPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyClientRole(['owner', 'manager']);
    }

    public function create(User $user, Client $client, Project $project): bool
    {
        return $this->canManagePair($user, $client, $project);
    }

    public function update(
        User $user,
        ProjectAccessPeriod $period,
        ?Client $client = null,
        ?Project $project = null,
    ): bool {
        $client ??= $period->client;
        $project ??= $period->project;

        return $client instanceof Client
            && $project instanceof Project
            && $this->canManagePair($user, $client, $project);
    }

    public function delete(User $user, ProjectAccessPeriod $period): bool
    {
        return $this->update($user, $period);
    }

    private function canManagePair(User $user, Client $client, Project $project): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasClientRole($client, ['owner', 'manager'])
            && $user->hasClientRole($project->client, ['owner', 'manager']);
    }
}
