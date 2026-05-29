<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientMemberRequest;
use App\Http\Requests\UpdateClientMemberRequest;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientMemberController extends Controller
{
    public function store(StoreClientMemberRequest $request, Client $client): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($client, $data, $request): void {
            $user = User::query()->firstOrCreate(
                ['email' => Str::lower($data['email'])],
                [
                    'name' => $data['name'],
                    'password' => Str::password(40),
                    'platform_role' => 'user',
                    'status' => 'active',
                ],
            );

            if ($data['is_default'] ?? false) {
                $this->clearDefaultClient($user);
            }

            ClientMembership::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $data['role'],
                    'status' => $data['status'],
                    'is_default' => $data['is_default'] ?? false,
                    'created_by' => $request->user()->id,
                ],
            );
        });

        return back()->with('success', 'Membre ajoute au client.');
    }

    public function update(
        UpdateClientMemberRequest $request,
        Client $client,
        ClientMembership $membership,
    ): RedirectResponse {
        $this->ensureMembershipBelongsToClient($client, $membership);

        $data = $request->validated();
        $this->ensureClientKeepsActiveOwner($client, $membership, $data['role'], $data['status']);

        DB::transaction(function () use ($data, $membership): void {
            if ($data['is_default'] ?? false) {
                $this->clearDefaultClient($membership->user);
            }

            $membership->update([
                'role' => $data['role'],
                'status' => $data['status'],
                'is_default' => $data['is_default'] ?? false,
            ]);
        });

        return back()->with('success', 'Membre mis a jour.');
    }

    public function destroy(Client $client, ClientMembership $membership): RedirectResponse
    {
        $this->authorizeMemberManagement($client);
        $this->ensureMembershipBelongsToClient($client, $membership);
        $this->ensureClientKeepsActiveOwner($client, $membership, null, null);

        $membership->delete();

        return back()->with('success', 'Membre retire du client.');
    }

    private function authorizeMemberManagement(Client $client): void
    {
        abort_unless(request()->user()?->can('manageMembers', $client), 403);
    }

    private function clearDefaultClient(User $user): void
    {
        $user->clientMemberships()->update(['is_default' => false]);
    }

    private function ensureMembershipBelongsToClient(Client $client, ClientMembership $membership): void
    {
        abort_unless($membership->client_id === $client->id, 404);
    }

    private function ensureClientKeepsActiveOwner(
        Client $client,
        ClientMembership $membership,
        ?string $newRole,
        ?string $newStatus,
    ): void {
        $activeOwners = $client->memberships()
            ->where('role', 'owner')
            ->where('status', 'active')
            ->whereKeyNot($membership->id)
            ->count();

        $membershipRemainsActiveOwner = $newRole === 'owner' && $newStatus === 'active';

        if ($activeOwners === 0 && ! $membershipRemainsActiveOwner) {
            throw ValidationException::withMessages([
                'role' => 'Un client doit conserver au moins un owner actif.',
            ]);
        }
    }
}
