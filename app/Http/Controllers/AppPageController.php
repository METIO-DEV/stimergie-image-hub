<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use App\Support\ClientLogoUrlResolver;
use App\Support\ImageUrlResolver;
use App\Support\ProjectAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppPageController extends Controller
{
    public function __construct(
        private readonly ClientLogoUrlResolver $clientLogos,
        private readonly ImageUrlResolver $imageUrls,
        private readonly ProjectAccess $projectAccess,
    ) {}

    public function gallery(Request $request): Response
    {
        $user = $request->user();
        $clientIds = $this->projectAccess->accessibleClientIds($user);
        $manageableClientIds = $this->manageableClientIds($user);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 100;
        $totalImages = Image::query()
            ->tap(fn ($query) => $this->applyPhotoBucketFilter($query))
            ->tap(fn ($query) => $this->projectAccess->applyImageVisibility($query, $request->user()))
            ->count();

        $images = Image::query()
            ->with([
                'client' => fn ($query) => $query
                    ->select('id', 'name', 'slug', 'logo_object_key', 'status')
                    ->withCount(['projects', 'images', 'memberships']),
                'project:id,name',
                'tags:id,name',
            ])
            ->tap(fn ($query) => $this->applyPhotoBucketFilter($query))
            ->tap(fn ($query) => $this->projectAccess->applyImageVisibility($query, $request->user()))
            ->latest()
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Image $image) => $this->imageSummary($image, $user, $manageableClientIds));

        return Inertia::render('Gallery/Index', [
            'images' => $images,
            'stats' => [
                'images' => $totalImages,
                'clients' => Client::query()
                    ->when($clientIds !== null, fn ($query) => $query->whereIn('id', $clientIds))
                    ->count(),
                'projects' => Project::query()
                    ->tap(fn ($query) => $this->projectAccess->applyProjectVisibility($query, $request->user()))
                    ->count(),
            ],
            'filters' => $this->filterOptions($request->user(), $clientIds),
            'bulkProjects' => $this->manageableProjectOptions($user, $manageableClientIds),
            'canBulkAssignImages' => $this->canManageClientContent($user),
            'pagination' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'total' => $totalImages,
            ],
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Contact/Index');
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'contact.requested',
            'properties' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Votre message a ete transmis a l equipe Stimergie.');
    }

    public function downloads(Request $request): Response
    {
        $user = $request->user();
        $clientIds = $this->projectAccess->accessibleClientIds($user);

        $downloads = DownloadJob::query()
            ->with('client:id,name')
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->when($clientIds !== null, fn ($query) => $query->where(function ($query) use ($clientIds) {
                $query->whereNull('client_id')->orWhereIn('client_id', $clientIds);
            }))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DownloadJob $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status,
                'isHd' => $job->is_hd,
                'imageCount' => $job->image_count,
                'clientName' => $job->client?->name,
                'processedAt' => $job->processed_at?->toIso8601String(),
                'expiresAt' => $job->download_url_expires_at?->toIso8601String(),
                'createdAt' => $job->created_at->toIso8601String(),
                'downloadUrl' => $job->status === 'ready' ? route('downloads.show', $job) : null,
            ]);

        return Inertia::render('Downloads/Index', [
            'downloads' => $downloads,
        ]);
    }

    public function images(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canManageClientContent($user), 403);

        $manageableClientIds = $this->manageableClientIds($user);

        $images = Image::query()
            ->with([
                'client' => fn ($query) => $query
                    ->select('id', 'name', 'slug', 'logo_object_key', 'status')
                    ->withCount(['projects', 'images', 'memberships']),
                'project:id,name',
                'tags:id,name',
            ])
            ->tap(fn ($query) => $this->applyPhotoBucketFilter($query))
            ->tap(fn ($query) => $this->applyManageableClientScope($query, $manageableClientIds))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Image $image) => $this->imageSummary($image, $user, $manageableClientIds));

        return Inertia::render('Images/Index', [
            'images' => $images,
            'canManageImages' => true,
            'filters' => [
                'clients' => $this->manageableClientOptions($manageableClientIds),
                'projects' => $this->manageableProjectOptions($user, $manageableClientIds),
            ],
        ]);
    }

    public function projects(Request $request): Response
    {
        $user = $request->user();
        $clientIds = $this->projectAccess->accessibleClientIds($user);
        $manageableClientIds = $this->manageableClientIds($user);

        $projects = Project::query()
            ->with('client:id,name,logo_object_key')
            ->withCount('images')
            ->tap(fn ($query) => $this->projectAccess->applyProjectVisibility($query, $request->user()))
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'type' => $project->type,
                'clientId' => $project->client_id,
                'clientName' => $project->client->name,
                'clientLogo' => $this->clientLogos->url($project->client),
                'sourceFolder' => $project->source_folder,
                'imagesCount' => $project->images_count,
                'createdAt' => $project->created_at->toIso8601String(),
                'canUpdate' => $this->canManageClientId($project->client_id, $manageableClientIds),
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $this->filterOptions($request->user(), $clientIds),
            'manageableClients' => $this->manageableClientOptions($manageableClientIds),
            'canCreateProject' => $this->canManageClientContent($user),
        ]);
    }

    public function users(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $clientIds = $this->projectAccess->accessibleClientIds($request->user());

        $users = User::query()
            ->with(['clientMemberships.client:id,name'])
            ->when($clientIds !== null, fn ($query) => $query->whereHas(
                'clientMemberships',
                fn ($membership) => $membership->whereIn('client_id', $clientIds),
            ))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'platformRole' => $user->platform_role,
                'role' => $this->legacyRole($user),
                'clients' => $user->clientMemberships->map(fn ($membership) => [
                    'clientId' => $membership->client?->id,
                    'clientName' => $membership->client?->name,
                    'role' => $membership->role,
                    'status' => $membership->status,
                ]),
                'clientIds' => $user->clientMemberships
                    ->pluck('client_id')
                    ->filter()
                    ->values(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'clients' => Client::query()
                ->when($clientIds !== null, fn ($query) => $query->whereIn('id', $clientIds))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                ]),
            'roles' => [
                ['value' => 'admin', 'label' => 'Administrateur'],
                ['value' => 'admin_client', 'label' => 'Admin Client'],
                ['value' => 'user', 'label' => 'Utilisateur'],
            ],
        ]);
    }

    public function accessPeriods(Request $request): Response
    {
        $clientIds = $this->projectAccess->accessibleClientIds($request->user());

        $periods = ProjectAccessPeriod::query()
            ->with(['client:id,name', 'project:id,name'])
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->latest()
            ->get()
            ->map(fn (ProjectAccessPeriod $period) => [
                'id' => $period->id,
                'clientName' => $period->client->name,
                'projectName' => $period->project->name,
                'isActive' => $period->is_active,
                'startsAt' => $period->starts_at?->toDateString(),
                'endsAt' => $period->ends_at?->toDateString(),
            ]);

        return Inertia::render('AccessPeriods/Index', [
            'periods' => $periods,
            'canManageAccessPeriods' => false,
        ]);
    }

    /**
     * @return array{clients: mixed, projects: mixed}
     */
    private function filterOptions(User $user, ?array $clientIds): array
    {
        return [
            'clients' => $this->clientOptions($clientIds),
            'projects' => Project::query()
                ->with('client:id,name')
                ->tap(fn ($query) => $this->projectAccess->applyProjectVisibility($query, $user))
                ->orderBy('name')
                ->get(['id', 'client_id', 'name'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'clientId' => $project->client_id,
                    'name' => $project->name,
                    'clientName' => $project->client?->name,
                ]),
        ];
    }

    private function clientOptions(?array $clientIds): mixed
    {
        return Client::query()
            ->when($clientIds !== null, fn ($query) => $query->whereIn('id', $clientIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ]);
    }

    private function manageableClientOptions(?array $manageableClientIds): mixed
    {
        return $this->clientOptions($manageableClientIds);
    }

    private function manageableProjectOptions(User $user, ?array $manageableClientIds): mixed
    {
        return Project::query()
            ->with('client:id,name')
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereIn('client_id', $manageableClientIds ?? []))
            ->orderBy('name')
            ->get(['id', 'client_id', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'clientId' => $project->client_id,
                'name' => $project->name,
                'clientName' => $project->client?->name,
            ]);
    }

    private function legacyRole(User $user): string
    {
        if ($user->platform_role === 'super_admin') {
            return 'admin';
        }

        return $user->platform_role === 'admin_client' ? 'admin_client' : 'user';
    }

    /**
     * @return array<string, mixed>
     */
    private function imageSummary(Image $image, User $user, ?array $manageableClientIds): array
    {
        return [
            'id' => $image->id,
            'title' => $image->title,
            'description' => $image->description,
            'orientation' => $image->orientation,
            'status' => $image->status,
            'clientName' => $image->client->name,
            'clientId' => $image->client_id,
            'client' => [
                'id' => $image->client->id,
                'name' => $image->client->name,
                'slug' => $image->client->slug,
                'logo' => $this->clientLogos->url($image->client),
                'status' => $image->client->status,
                'projectsCount' => $image->client->projects_count,
                'imagesCount' => $image->client->images_count,
                'membersCount' => $image->client->memberships_count,
            ],
            'projectName' => $image->project->name,
            'projectId' => $image->project_id,
            'thumbUrl' => $this->imageUrls->thumbnailUrl($image),
            'imageUrl' => $this->imageUrls->displayUrl($image),
            'downloadUrl' => route('images.download', ['image' => $image, 'variant' => 'hd']),
            'webDownloadUrl' => route('images.download', ['image' => $image, 'variant' => 'web']),
            'hdDownloadUrl' => route('images.download', ['image' => $image, 'variant' => 'hd']),
            'width' => $image->width,
            'height' => $image->height,
            'tags' => $image->tags->pluck('name')->values(),
            'createdAt' => $image->created_at->toIso8601String(),
            'canManage' => $this->canManageClientId($image->client_id, $manageableClientIds),
        ];
    }

    private function applyPhotoBucketFilter($query): void
    {
        $query
            ->where('storage_provider', 'scaleway')
            ->where('object_key_original', 'like', 'photos/%');
    }

    private function canManageClientContent(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyClientRole(['owner', 'manager']);
    }

    /**
     * @return array<int>|null
     */
    private function manageableClientIds(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        return $user->clientMemberships()
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])
            ->pluck('client_id')
            ->all();
    }

    private function canManageClientId(int $clientId, ?array $manageableClientIds): bool
    {
        return $manageableClientIds === null || in_array($clientId, $manageableClientIds, true);
    }

    private function applyManageableClientScope($query, ?array $manageableClientIds): void
    {
        if ($manageableClientIds === null) {
            return;
        }

        $query->whereIn('client_id', $manageableClientIds);
    }
}
