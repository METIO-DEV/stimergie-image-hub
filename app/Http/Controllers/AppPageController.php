<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppPageController extends Controller
{
    public function gallery(Request $request): Response
    {
        $clientIds = $this->accessibleClientIds($request);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 100;
        $totalImages = Image::query()
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->count();

        $images = Image::query()
            ->with([
                'client' => fn ($query) => $query
                    ->select('id', 'name', 'slug', 'legacy_logo_url', 'status')
                    ->withCount(['projects', 'images', 'memberships']),
                'project:id,name',
                'tags:id,name',
            ])
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->latest()
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Image $image) => $this->imageSummary($image));

        return Inertia::render('Gallery/Index', [
            'images' => $images,
            'stats' => [
                'images' => $totalImages,
                'clients' => Client::query()
                    ->when($clientIds !== null, fn ($query) => $query->whereIn('id', $clientIds))
                    ->count(),
                'projects' => Project::query()
                    ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
                    ->count(),
            ],
            'filters' => $this->filterOptions($clientIds),
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
        $clientIds = $this->accessibleClientIds($request);

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
            ]);

        return Inertia::render('Downloads/Index', [
            'downloads' => $downloads,
        ]);
    }

    public function images(Request $request): Response
    {
        $clientIds = $this->accessibleClientIds($request);

        $images = Image::query()
            ->with([
                'client' => fn ($query) => $query
                    ->select('id', 'name', 'slug', 'legacy_logo_url', 'status')
                    ->withCount(['projects', 'images', 'memberships']),
                'project:id,name',
                'tags:id,name',
            ])
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Image $image) => $this->imageSummary($image));

        return Inertia::render('Images/Index', [
            'images' => $images,
            'canManageImages' => $request->user()->isSuperAdmin(),
            'filters' => $this->filterOptions($clientIds),
        ]);
    }

    public function projects(Request $request): Response
    {
        $clientIds = $this->accessibleClientIds($request);

        $projects = Project::query()
            ->with('client:id,name')
            ->withCount('images')
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'type' => $project->type,
                'clientName' => $project->client->name,
                'clientLogo' => $project->client->legacy_logo_url,
                'sourceFolder' => $project->source_folder,
                'imagesCount' => $project->images_count,
                'createdAt' => $project->created_at->toIso8601String(),
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $this->filterOptions($clientIds),
        ]);
    }

    public function users(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        $clientIds = $this->accessibleClientIds($request);

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
                'role' => $this->legacyRole($user->platform_role),
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
        $clientIds = $this->accessibleClientIds($request);

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
        ]);
    }

    /**
     * @return array<int>|null
     */
    private function accessibleClientIds(Request $request): ?array
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return null;
        }

        return $user->clientMemberships()
            ->where('status', 'active')
            ->pluck('client_id')
            ->all();
    }

    /**
     * @return array{clients: mixed, projects: mixed}
     */
    private function filterOptions(?array $clientIds): array
    {
        return [
            'clients' => Client::query()
                ->when($clientIds !== null, fn ($query) => $query->whereIn('id', $clientIds))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                ]),
            'projects' => Project::query()
                ->with('client:id,name')
                ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
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

    private function legacyRole(?string $platformRole): string
    {
        return $platformRole === 'super_admin' ? 'admin' : 'user';
    }

    /**
     * @return array<string, mixed>
     */
    private function imageSummary(Image $image): array
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
                'logo' => $image->client->legacy_logo_url,
                'status' => $image->client->status,
                'projectsCount' => $image->client->projects_count,
                'imagesCount' => $image->client->images_count,
                'membersCount' => $image->client->memberships_count,
            ],
            'projectName' => $image->project->name,
            'projectId' => $image->project_id,
            'thumbUrl' => $image->legacy_thumbnail_url ?: $image->legacy_url,
            'imageUrl' => $image->legacy_url ?: $image->legacy_thumbnail_url,
            'downloadUrl' => $image->legacy_url ?: $image->legacy_thumbnail_url,
            'width' => $image->width,
            'height' => $image->height,
            'tags' => $image->tags->pluck('name')->values(),
            'createdAt' => $image->created_at->toIso8601String(),
        ];
    }
}
