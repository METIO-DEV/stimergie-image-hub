<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Support\ClientLogoUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientLogoUrlResolver $clientLogos,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if (! Gate::allows('viewAny', Client::class)) {
            return redirect()
                ->route('gallery.index')
                ->with('warning', "La gestion des clients est reservee aux Admin Client owner/manager.");
        }

        $user = $request->user();

        $query = Client::query()
            ->withCount(['projects', 'images', 'memberships'])
            ->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $query->whereHas('memberships', fn ($membership) => $membership
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'manager']));
        }

        return Inertia::render('Clients/Index', [
            'clients' => $query->get()->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
                'status' => $client->status,
                'logo' => $this->clientLogos->url($client),
                'projectsCount' => $client->projects_count,
                'imagesCount' => $client->images_count,
                'membersCount' => $client->memberships_count,
                'canUpdate' => $request->user()->can('update', $client),
                'canManageMembers' => $request->user()->can('manageMembers', $client),
            ]),
            'canCreateClient' => Gate::allows('create', Client::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('Clients/Create', [
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $slug = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name']);

        $client = DB::transaction(function () use ($data, $request, $slug): Client {
            $client = Client::create([
                'name' => $data['name'],
                'slug' => $slug,
                'status' => $data['status'],
            ]);

            if ($request->hasFile('logo')) {
                $client->forceFill([
                    'logo_object_key' => $this->storeLogo($client, $request->file('logo')),
                ])->save();
            }

            $client->memberships()->create([
                'user_id' => $request->user()->id,
                'role' => 'owner',
                'status' => 'active',
                'is_default' => false,
                'created_by' => $request->user()->id,
            ]);

            return $client;
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client cree.');
    }

    public function show(Request $request, Client $client): Response
    {
        Gate::authorize('view', $client);

        $client->load([
            'memberships' => fn ($query) => $query
                ->with('user:id,name,email,status')
                ->orderByDesc('is_default')
                ->orderBy('role')
                ->orderBy('id'),
            'projects' => fn ($query) => $query
                ->latest()
                ->limit(8),
        ])->loadCount(['projects', 'images', 'memberships']);

        return Inertia::render('Clients/Show', [
            'client' => $this->clientDetails($client),
            'canUpdateClient' => $request->user()->can('update', $client),
            'canManageMembers' => $request->user()->can('manageMembers', $client),
            'roleOptions' => $this->memberRoles(),
            'membershipStatuses' => $this->membershipStatuses(),
        ]);
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
                'status' => $client->status,
                'logo' => $this->clientLogos->url($client),
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $data = $request->validated();

        $client->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name'], $client),
            'status' => $data['status'],
        ]);

        if ($request->hasFile('logo')) {
            $oldLogo = $client->logo_object_key;
            $client->forceFill([
                'logo_object_key' => $this->storeLogo($client, $request->file('logo')),
            ])->save();

            if ($oldLogo && $oldLogo !== $client->logo_object_key) {
                Storage::disk($this->imageDisk())->delete($oldLogo);
            }
        }

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client mis a jour.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => 'active', 'label' => 'Actif'],
            ['value' => 'paused', 'label' => 'En pause'],
            ['value' => 'archived', 'label' => 'Archive'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function memberRoles(): array
    {
        return [
            ['value' => 'owner', 'label' => 'Owner'],
            ['value' => 'manager', 'label' => 'Manager'],
            ['value' => 'member', 'label' => 'Membre'],
            ['value' => 'viewer', 'label' => 'Lecture seule'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function membershipStatuses(): array
    {
        return [
            ['value' => 'active', 'label' => 'Actif'],
            ['value' => 'paused', 'label' => 'En pause'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientDetails(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'slug' => $client->slug,
            'status' => $client->status,
            'logo' => $this->clientLogos->url($client),
            'projectsCount' => $client->projects_count,
            'imagesCount' => $client->images_count,
            'membersCount' => $client->memberships_count,
            'memberships' => $client->memberships->map(fn ($membership) => [
                'id' => $membership->id,
                'role' => $membership->role,
                'status' => $membership->status,
                'isDefault' => $membership->is_default,
                'user' => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'status' => $membership->user->status,
                ],
            ]),
            'projects' => $client->projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'type' => $project->type,
            ]),
        ];
    }

    private function uniqueSlug(string $value, ?Client $ignore = null): string
    {
        $base = Str::slug($value) ?: 'client';
        $slug = $base;
        $suffix = 2;

        while (Client::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeLogo(Client $client, mixed $file): string
    {
        $extension = $file->extension() ?: $file->guessExtension() ?: 'bin';
        $filename = 'logo-'.now()->format('YmdHis').'-'.Str::random(8).'.'.Str::lower($extension);

        return $file->storeAs("clients/{$client->id}", $filename, [
            'disk' => $this->imageDisk(),
            'visibility' => 'public',
        ]);
    }

    private function imageDisk(): string
    {
        return (string) config('filesystems.image_disk', 'scaleway');
    }
}
