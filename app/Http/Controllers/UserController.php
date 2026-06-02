<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ClientMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            $user = User::create([
                'name' => $this->fullName($data['first_name'], $data['last_name'] ?? ''),
                'email' => Str::lower($data['email']),
                'password' => $data['password'] ?: Str::password(40),
                'platform_role' => $this->platformRole($data['role']),
                'status' => $data['status'],
            ]);

            $this->syncClientMemberships($user, $data['client_ids'] ?? [], $data['role'], $data['status'], $request->user()->id);
        });

        return back()->with('success', 'Utilisateur cree.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $user): void {
            $payload = [
                'name' => $this->fullName($data['first_name'], $data['last_name'] ?? ''),
                'email' => Str::lower($data['email']),
                'platform_role' => $this->platformRole($data['role']),
                'status' => $data['status'],
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);
            $this->syncClientMemberships($user, $data['client_ids'] ?? [], $data['role'], $data['status'], $request->user()->id);
        });

        return back()->with('success', 'Utilisateur mis a jour.');
    }

    /**
     * @param array<int, int|string> $clientIds
     */
    private function syncClientMemberships(User $user, array $clientIds, string $role, string $status, int $actorId): void
    {
        $membershipRole = $role === 'admin_client' ? 'manager' : 'viewer';
        $normalizedClientIds = collect($clientIds)
            ->map(fn ($clientId) => (int) $clientId)
            ->filter()
            ->unique()
            ->values();

        if ($normalizedClientIds->isEmpty()) {
            $user->clientMemberships()->delete();

            return;
        }

        $user->clientMemberships()
            ->whereNotIn('client_id', $normalizedClientIds)
            ->delete();

        $normalizedClientIds->each(function (int $clientId) use ($actorId, $membershipRole, $status, $user): void {
            ClientMembership::query()->updateOrCreate(
                [
                    'client_id' => $clientId,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $membershipRole,
                    'status' => $status,
                    'is_default' => false,
                    'created_by' => $actorId,
                ],
            );
        });
    }

    private function fullName(string $firstName, string $lastName): string
    {
        return trim("{$firstName} {$lastName}");
    }

    private function platformRole(string $role): string
    {
        return match ($role) {
            'admin' => 'super_admin',
            'admin_client' => 'admin_client',
            default => 'user',
        };
    }
}
