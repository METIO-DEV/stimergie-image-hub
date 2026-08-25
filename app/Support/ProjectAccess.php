<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ProjectAccess
{
    /**
     * @return array<int>|null
     */
    public function accessibleClientIds(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        return $user->clientMemberships()
            ->where('status', 'active')
            ->pluck('client_id')
            ->all();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function applyProjectVisibility(Builder $query, User $user): Builder
    {
        $clientIds = $this->accessibleClientIds($user);

        if ($clientIds === null) {
            return $query;
        }

        if ($clientIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($clientIds): void {
            $query
                ->where(function (Builder $query) use ($clientIds): void {
                    $query
                        ->whereIn('client_id', $clientIds)
                        ->where(function (Builder $query) use ($clientIds): void {
                            $query
                                ->whereDoesntHave(
                                    'accessPeriods',
                                    fn (Builder $periods) => $periods->whereIn('client_id', $clientIds),
                                )
                                ->orWhereHas(
                                    'accessPeriods',
                                    fn (Builder $periods) => $this->applyActivePeriod($periods, $clientIds),
                                );
                        });
                })
                ->orWhereHas(
                    'accessPeriods',
                    fn (Builder $periods) => $this->applyActivePeriod($periods, $clientIds),
                );
        });
    }

    /**
     * @param  Builder<Image>  $query
     * @return Builder<Image>
     */
    public function applyImageVisibility(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $clientIds = $this->accessibleClientIds($user);

        if ($clientIds === [] || $clientIds === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($clientIds, $user): void {
            $query
                ->whereHas(
                    'project',
                    fn (Builder $projects) => $this->applyProjectVisibility($projects, $user),
                )
                ->orWhereHas(
                    'sharedClients',
                    fn (Builder $clients) => $this->applyActiveImageShare($clients, $clientIds),
                );
        });
    }

    public function userCanViewImage(User $user, Image $image): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $clientIds = $this->accessibleClientIds($user);

        if ($clientIds === [] || $clientIds === null) {
            return false;
        }

        $image->loadMissing('project.accessPeriods', 'sharedClients');

        return $this->projectVisibleToClientIds($image->project, $clientIds)
            || $image->sharedClients
                ->contains(fn ($client) => in_array($client->id, $clientIds, true)
                    && ($client->pivot->expires_at === null || now()->lte($client->pivot->expires_at)));
    }

    /**
     * @param  array<int>  $clientIds
     */
    private function projectVisibleToClientIds(Project $project, array $clientIds): bool
    {
        $periodsForUserClients = $project->accessPeriods
            ->filter(fn (ProjectAccessPeriod $period) => in_array($period->client_id, $clientIds, true));
        $hasActivePeriod = $periodsForUserClients
            ->contains(fn (ProjectAccessPeriod $period) => $this->periodIsActive($period));

        if ($hasActivePeriod) {
            return true;
        }

        return in_array($project->client_id, $clientIds, true)
            && $periodsForUserClients->isEmpty();
    }

    /**
     * @param  Builder<ProjectAccessPeriod>  $query
     * @param  array<int>  $clientIds
     * @return Builder<ProjectAccessPeriod>
     */
    private function applyActivePeriod(Builder $query, array $clientIds): Builder
    {
        $now = now();

        return $query
            ->whereIn('client_id', $clientIds)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * @param  Builder<Client>  $query
     * @param  array<int>  $clientIds
     * @return Builder<Client>
     */
    private function applyActiveImageShare(Builder $query, array $clientIds): Builder
    {
        return $query
            ->whereIn('clients.id', $clientIds)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('image_client_shares.expires_at')
                    ->orWhere('image_client_shares.expires_at', '>=', now());
            });
    }

    private function periodIsActive(ProjectAccessPeriod $period): bool
    {
        $now = Carbon::now();

        return $period->is_active
            && ($period->starts_at === null || $period->starts_at->lte($now))
            && ($period->ends_at === null || $period->ends_at->gte($now));
    }
}
