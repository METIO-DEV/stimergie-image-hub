<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Import;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\Tag;
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
        $perPage = 60;
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
                'project:id,name,client_id',
                'tags:id,name',
                'sharedClients:id,name',
                'variants:id,image_id,kind,object_key,mime_type,width,height,size_bytes',
                'latestRightsExtensionRequest',
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
                'tag' => $galleryFilters['tag'],
                'dateFrom' => $galleryFilters['dateFrom'],
                'dateTo' => $galleryFilters['dateTo'],
            ],
            'bulkProjects' => $this->manageableProjectOptions($user, $manageableClientIds),
            'canAddImages' => $this->canManageClientContent($user),
            'canBulkAssignImages' => $this->canManageClientContent($user),
            'canCreateSharedAlbums' => $this->canManageClientContent($user),
            'pagination' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'total' => $totalImages,
            ],
        ]);
    }

    /**
     * @return array{search: string, orientation: string, clientId: int|null, projectId: int|null, tag: string, dateFrom: string, dateTo: string}
     */
    private function galleryFilters(Request $request): array
    {
        $orientation = (string) $request->query('orientation', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'orientation' => in_array($orientation, ['landscape', 'portrait', 'square'], true) ? $orientation : '',
            'clientId' => $request->query('client_id') ? max(1, (int) $request->query('client_id')) : null,
            'projectId' => $request->query('project_id') ? max(1, (int) $request->query('project_id')) : null,
            'tag' => trim((string) $request->query('tag', '')),
            'dateFrom' => $this->dateFilter($request->query('date_from')),
            'dateTo' => $this->dateFilter($request->query('date_to')),
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
        $imageFilters = $this->manageableImageFilters($request);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 20;

        $baseQuery = Image::query()
            ->tap(fn ($query) => $this->applyPhotoBucketFilter($query))
            ->tap(fn ($query) => $this->applyManageableClientScope($query, $manageableClientIds))
            ->tap(fn ($query) => $this->applyManageableImageFilters($query, $imageFilters));

        $totalImages = (clone $baseQuery)->count();

        $images = (clone $baseQuery)
            ->with([
                'client' => fn ($query) => $query
                    ->select('id', 'name', 'slug', 'logo_object_key', 'status')
                    ->withCount(['projects', 'images', 'memberships']),
                'project:id,name,client_id',
                'tags:id,name',
                'sharedClients:id,name',
                'variants:id,image_id,kind,object_key,mime_type,width,height,size_bytes',
                'latestRightsExtensionRequest',
            ])
            ->latest()
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Image $image) => $this->imageSummary($image, $user, $manageableClientIds));

        return Inertia::render('Images/Index', [
            'images' => $images,
            ...$this->importTrackingData($manageableClientIds),
            'canManageImages' => true,
            'filters' => [
                'clients' => $this->manageableClientOptions($manageableClientIds),
                'projects' => $this->manageableProjectOptions($user, $manageableClientIds),
            ],
            'activeFilters' => [
                'search' => $imageFilters['search'],
                'orientation' => $imageFilters['orientation'],
                'clientId' => $imageFilters['clientId'] ? (string) $imageFilters['clientId'] : '',
                'tag' => $imageFilters['tag'],
            ],
            'imagePagination' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'total' => $totalImages,
            ],
            'rightsExtensionRequests' => $this->rightsExtensionRequestSummaries($manageableClientIds),
            'rightsExtensionRequestStatuses' => $this->rightsExtensionRequestStatusOptions(),
        ]);
    }

    public function imports(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canManageClientContent($user), 403);

        $manageableClientIds = $this->manageableClientIds($user);

        return Inertia::render('Imports/Index', [
            ...$this->importTrackingData($manageableClientIds),
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
        $user = $request->user();
        $clientIds = $this->projectAccess->accessibleClientIds($user);
        $manageableClientIds = $this->manageableClientIds($user);

        $periods = ProjectAccessPeriod::query()
            ->with(['client:id,name', 'project:id,name'])
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->latest()
            ->get()
            ->map(fn (ProjectAccessPeriod $period) => [
                'id' => $period->id,
                'clientId' => $period->client_id,
                'clientName' => $period->client->name,
                'projectId' => $period->project_id,
                'projectName' => $period->project->name,
                'isActive' => $period->is_active,
                'startsAt' => $period->starts_at?->toDateString(),
                'endsAt' => $period->ends_at?->toDateString(),
                'status' => $this->accessPeriodStatus($period),
                'canUpdate' => $request->user()->can('update', $period),
                'canDelete' => $request->user()->can('delete', $period),
            ]);

        return Inertia::render('AccessPeriods/Index', [
            'periods' => $periods,
            'clients' => $this->manageableClientOptions($manageableClientIds),
            'projects' => $this->manageableProjectOptions($user, $manageableClientIds),
            'canManageAccessPeriods' => $request->user()->can('viewAny', ProjectAccessPeriod::class),
        ]);
    }

    /**
     * @return array{clients: mixed, projects: mixed, tags: mixed}
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
            'tags' => Tag::query()
                ->whereHas('images', fn ($query) => $this->projectAccess->applyImageVisibility($query, $user))
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name'])
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
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
            ->get(['id', 'client_id', 'name', 'source_folder'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'clientId' => $project->client_id,
                'name' => $project->name,
                'clientName' => $project->client?->name,
                'sourceFolder' => $project->source_folder,
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
        $rightsStatus = $image->rightsStatus();
        $canDownload = ! $image->rightsAreExpired();

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
            'thumbUrl' => $this->imageUrls->temporaryThumbnailUrl($image),
            'imageUrl' => $this->imageUrls->temporaryDisplayUrl($image),
            'downloadUrl' => $canDownload ? route('images.download', ['image' => $image, 'variant' => 'hd']) : null,
            'webDownloadUrl' => $canDownload ? route('images.download', ['image' => $image, 'variant' => 'web']) : null,
            'hdDownloadUrl' => $canDownload ? route('images.download', ['image' => $image, 'variant' => 'hd']) : null,
            'hasWebVariant' => $this->hasStandaloneWebVariant($image),
            'width' => $image->width,
            'height' => $image->height,
            'rightsStartsAt' => $image->rights_starts_at?->toDateString(),
            'rightsEndsAt' => $image->rights_ends_at?->toDateString(),
            'rightsStatus' => $rightsStatus,
            'rightsStatusLabel' => $this->rightsStatusLabel($rightsStatus),
            'rightsExtensionRequestedAt' => $image->rights_extension_requested_at?->toIso8601String(),
            'rightsExtensionRequest' => $this->latestRightsExtensionRequestSummary($image),
            'canRequestRightsExtension' => $this->canRequestRightsExtension($image, $user),
            'rightsExtensionRequestUrl' => route('images.rights-extension', $image),
            'tags' => $image->tags->pluck('name')->values(),
            'sharedClients' => $image->sharedClients
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'expiresAt' => $client->pivot->expires_at,
                ])
                ->values(),
            'createdAt' => $image->created_at->toIso8601String(),
            'canManage' => $this->canManageClientId($image->client_id, $manageableClientIds),
        ];
    }

    private function rightsStatusLabel(string $status): string
    {
        return match ($status) {
            'expired' => 'Cession expirée',
            'expiring_soon' => 'Cession bientôt expirée',
            'active' => 'Cession active',
            default => 'Cession non limitée',
        };
    }

    private function latestRightsExtensionRequestSummary(Image $image): ?array
    {
        $request = $image->relationLoaded('latestRightsExtensionRequest')
            ? $image->latestRightsExtensionRequest
            : null;

        if (! $request instanceof ImageRightsExtensionRequest) {
            return null;
        }

        return [
            'id' => $request->id,
            'status' => $request->status,
            'statusLabel' => $request->statusLabel(),
            'requestedAt' => $request->created_at?->toIso8601String(),
            'rightsEndsAt' => $request->rights_ends_at?->toDateString(),
        ];
    }

    private function hasStandaloneWebVariant(Image $image): bool
    {
        $objectKey = null;

        if ($image->relationLoaded('variants')) {
            $objectKey = $image->variants
                ->firstWhere('kind', 'web')
                ?->object_key;
        }

        $objectKey = $objectKey ?: $image->object_key_web;

        return is_string($objectKey)
            && $objectKey !== ''
            && ! in_array($objectKey, array_filter([
                $image->object_key_original,
                $image->object_key_hd,
            ]), true);
    }

    private function canRequestRightsExtension(Image $image, User $user): bool
    {
        return $image->canRequestRightsExtension()
            && $this->projectAccess->userCanViewImage($user, $image);
    }

    private function applyPhotoBucketFilter($query): void
    {
        $query
            ->where('storage_provider', 'scaleway')
            ->where('object_key_original', 'like', 'photos/%');
    }

    /**
     * @param  array{search: string, orientation: string, clientId: int|null, projectId: int|null, tag: string, dateFrom: string, dateTo: string}  $filters
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
            ->when($filters['projectId'] !== null, fn ($query) => $query->where('project_id', $filters['projectId']))
            ->when($filters['tag'] !== '', function ($query) use ($filters): void {
                $tag = $filters['tag'];

                $query->whereHas('tags', fn ($tags) => $tags->where('name', $tag));
            })
            ->when($filters['dateFrom'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['dateTo']));
    }

    private function dateFilter(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * @return array{search: string, orientation: string, clientId: int|null, tag: string}
     */
    private function manageableImageFilters(Request $request): array
    {
        $orientation = (string) $request->query('orientation', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'orientation' => in_array($orientation, ['landscape', 'portrait', 'square'], true) ? $orientation : '',
            'clientId' => $request->query('client_id') ? max(1, (int) $request->query('client_id')) : null,
            'tag' => trim((string) $request->query('tag', '')),
        ];
    }

    /**
     * @param  array{search: string, orientation: string, clientId: int|null, tag: string}  $filters
     */
    private function applyManageableImageFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['orientation'] !== '', fn ($query) => $query->where('orientation', $filters['orientation']))
            ->when($filters['clientId'] !== null, fn ($query) => $query->where('client_id', $filters['clientId']))
            ->when($filters['tag'] !== '', function ($query) use ($filters): void {
                $tag = $filters['tag'];

                $query->whereHas('tags', fn ($tags) => $tags->where('name', 'like', "%{$tag}%"));
            });
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

    private function accessPeriodStatus(ProjectAccessPeriod $period): string
    {
        if (! $period->is_active) {
            return 'inactive';
        }

        if ($period->starts_at && $period->starts_at->isFuture()) {
            return 'upcoming';
        }

        if ($period->ends_at && $period->ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
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

    /**
     * @return array{imports: mixed, stats: array{total: int, active: int, failed: int, completed: int}}
     */
    private function importTrackingData(?array $manageableClientIds): array
    {
        $query = Import::query()
            ->with(['client:id,name', 'project:id,name', 'starter:id,name,email'])
            ->where('source', 'folder_upload')
            ->tap(fn ($query) => $this->applyImportManageableClientScope($query, $manageableClientIds));

        return [
            'imports' => (clone $query)
                ->latest()
                ->limit(40)
                ->get()
                ->map(fn (Import $import) => $this->importSummary($import)),
            'stats' => [
                'total' => (clone $query)->count(),
                'active' => (clone $query)->whereIn('status', ['pending', 'processing'])->count(),
                'failed' => (clone $query)->where('status', 'failed')->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rightsExtensionRequestSummaries(?array $manageableClientIds): array
    {
        return ImageRightsExtensionRequest::query()
            ->with([
                'image:id,title',
                'client:id,name',
                'project:id,name',
                'requester:id,name,email',
            ])
            ->when($manageableClientIds !== null, fn ($query) => $query->whereIn('client_id', $manageableClientIds))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ImageRightsExtensionRequest $request) => [
                'id' => $request->id,
                'status' => $request->status,
                'statusLabel' => $request->statusLabel(),
                'imageId' => $request->image_id,
                'imageTitle' => $request->image?->title,
                'clientName' => $request->client?->name,
                'projectName' => $request->project?->name,
                'rightsEndsAt' => $request->rights_ends_at?->toDateString(),
                'requestedBy' => $request->requester?->name ?: $request->requester?->email,
                'requestedAt' => $request->created_at?->toIso8601String(),
                'resolvedAt' => $request->resolved_at?->toIso8601String(),
                'updateUrl' => route('image-rights-extension-requests.update', $request),
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function rightsExtensionRequestStatusOptions(): array
    {
        return collect(ImageRightsExtensionRequest::STATUSES)
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => (new ImageRightsExtensionRequest(['status' => $status]))->statusLabel(),
            ])
            ->all();
    }
}
