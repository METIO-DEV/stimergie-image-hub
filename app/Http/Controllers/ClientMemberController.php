<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientMemberRequest;
use App\Http\Requests\UpdateClientMemberRequest;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\User;
use App\Support\UserInvitationMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClientMemberController extends Controller
{
    public function __construct(private readonly UserInvitationMailer $invitations) {}

    public function store(StoreClientMemberRequest $request, Client $client): RedirectResponse
    {
        $data = $request->validated();

        [$user, $wasCreated] = DB::transaction(function () use ($client, $data, $request): array {
            $user = User::query()->firstOrCreate(
                ['email' => Str::lower($data['email'])],
                [
                    'name' => $data['name'],
                    'password' => Str::password(40),
                    'platform_role' => 'user',
                    'status' => 'active',
                ],
            );

            $this->ensureUserCanHoldClientRole($user, $data['role']);

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

            return [$user, $user->wasRecentlyCreated];
        });

        $invitationSent = true;

        if ($wasCreated) {
            try {
                $invitationSent = $this->invitations->send($user);
            } catch (Throwable) {
                $invitationSent = false;
            }
        }

        if (! $invitationSent) {
            return back()
                ->with('success', "Membre ajouté à l'entreprise.")
                ->with('warning', "L'email d'invitation n'a pas pu être envoyé. Vérifiez la configuration Brevo.");
        }

        return back()->with('success', "Membre ajouté à l'entreprise.");
    }

    public function update(
        UpdateClientMemberRequest $request,
        Client $client,
        ClientMembership $membership,
    ): RedirectResponse {
        $this->ensureMembershipBelongsToClient($client, $membership);

        $data = $request->validated();
        $this->ensureUserCanHoldClientRole($membership->user, $data['role']);
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

        return back()->with('success', 'Membre mis à jour.');
    }

    public function destroy(Client $client, ClientMembership $membership): RedirectResponse
    {
        $this->authorizeMemberManagement($client);
        $this->ensureMembershipBelongsToClient($client, $membership);
        $this->ensureClientKeepsActiveOwner($client, $membership, null, null);

        $membership->delete();

        return back()->with('success', "Membre retiré de l'entreprise.");
    }

    private function authorizeMemberManagement(Client $client): void
    {
        abort_unless(request()->user()?->can('manageMembers', $client), 403);
    }

    private function clearDefaultClient(User $user): void
    {
        $user->clientMemberships()->update(['is_default' => false]);
    }

    private function ensureUserCanHoldClientRole(User $user, string $role): void
    {
        if (! in_array($role, ['owner', 'manager'], true) || $user->canHoldClientManagementRole()) {
            return;
        }

        throw ValidationException::withMessages([
            'role' => "Cet utilisateur doit d'abord être passé en Admin Client avant de recevoir un rôle Owner ou Manager.",
        ]);
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
                'role' => 'Une entreprise doit conserver au moins un owner actif.',
            ]);
        }
    }
}
