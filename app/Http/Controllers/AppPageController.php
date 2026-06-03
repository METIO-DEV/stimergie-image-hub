<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\Import;
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
        $galleryFilters = $this->galleryFilters($request);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 100;
        $totalImages = Image::query()
            ->tap(fn ($query) => $this->applyPhotoBucketFilter($query))
            ->tap(fn ($query) => $this->projectAccess->applyImageVisibility($query, $request->user()))
            ->tap(fn ($query) => $this->applyGalleryFilters($query, $galleryFilters))
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
            ->tap(fn ($query) => $this->applyGalleryFilters($query, $galleryFilters))
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
            'activeFilters' => [
                'search' => $galleryFilters['search'],
                'orientation' => $galleryFilters['orientation'],
                'clientId' => $galleryFilters['clientId'] ? (string) $galleryFilters['clientId'] : '',
                'projectId' => $galleryFilters['projectId'] ? (string) $galleryFilters['projectId'] : '',
            ],
            'bulkProjects' => $this->manageableProjectOptions($user, $manageableClientIds),
            'canBulkAssignImages' => $this->canManageClientContent($user),
            'pagination' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'total' => $totalImages,
            ],
        ]);
    }

    /**
     * @return array{search: string, orientation: string, clientId: int|null, projectId: int|null}
     */
    private function galleryFilters(Request $request): array
    {
        $orientation = (string) $request->query('orientation', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'orientation' => in_array($orientation, ['landscape', 'portrait', 'square'], true) ? $orientation : '',
            'clientId' => $request->query('client_id') ? max(1, (int) $request->query('client_id')) : null,
            'projectId' => $request->query('project_id') ? max(1, (int) $request->query('project_id')) : null,
        ];
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

        return back()->with('success', "Votre message a été transmis à l'équipe Stimergie.");
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

    public function imports(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canManageClientContent($user), 403);

        $manageableClientIds = $this->manageableClientIds($user);
        $query = Import::query()
            ->with(['client:id,name', 'project:id,name', 'starter:id,name,email'])
            ->where('source', 'folder_upload')
            ->tap(fn ($query) => $this->applyImportManageableClientScope($query, $manageableClientIds));

        $imports = (clone $query)
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (Import $import) => $this->importSummary($import));

        return Inertia::render('Imports/Index', [
            'imports' => $imports,
            'stats' => [
                'total' => (clone $query)->count(),
                'active' => (clone $query)->whereIn('status', ['pending', 'processing'])->count(),
                'failed' => (clone $query)->where('status', 'failed')->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
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
                'canDelete' => $this->canManageClientId($project->client_id, $manageableClientIds),
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

    /**
     * @param  array{search: string, orientation: string, clientId: int|null, projectId: int|null}  $filters
     */
    private function applyGalleryFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['orientation'] !== '', fn ($query) => $query->where('orientation', $filters['orientation']))
            ->when($filters['clientId'] !== null, fn ($query) => $query->where('client_id', $filters['clientId']))
            ->when($filters['projectId'] !== null, fn ($query) => $query->where('project_id', $filters['projectId']));
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

    private function applyImportManageableClientScope($query, ?array $manageableClientIds): void
    {
        if ($manageableClientIds === null) {
            return;
        }

        $query->whereIn('client_id', $manageableClientIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function importSummary(Import $import): array
    {
        $items = $import->items()
            ->with('image:id,title')
            ->latest()
            ->limit(30)
            ->get();

        return [
            'id' => $import->id,
            'status' => $import->status,
            'clientName' => $import->client?->name,
            'projectName' => $import->project?->name,
            'startedBy' => $import->starter?->name ?: $import->starter?->email,
            'totalItems' => $import->total_items,
            'uploadedItems' => $import->uploaded_items,
            'processedItems' => $import->processed_items,
            'failedItems' => $import->failed_items,
            'duplicateItems' => $import->duplicate_items,
            'totalBytes' => $import->total_bytes,
            'startedAt' => $import->started_at?->toIso8601String(),
            'finishedAt' => $import->finished_at?->toIso8601String(),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'imageId' => $item->image_id,
                'imageTitle' => $item->image?->title,
                'filename' => $item->original_filename ?: $item->source_identifier,
                'relativePath' => $item->relative_path,
                'status' => $item->status,
                'sizeBytes' => $item->size_bytes,
                'attempts' => $item->attempts,
                'error' => $item->error_details,
                'objectKeyOriginal' => $item->object_key_original,
                'processedAt' => $item->processed_at?->toIso8601String(),
            ]),
        ];
    }
}
