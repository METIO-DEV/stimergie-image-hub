<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OperationalLogController extends Controller
{
    private const MAX_ROWS = 120;

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin(), 403);

        $filters = $this->filters($request);

        return Inertia::render('Operations/Index', [
            'summary' => $this->summary(),
            'downloads' => $this->downloadRows($filters),
            'rights' => $this->rightsRows($filters),
            'extensionRequests' => $this->extensionRequestRows($filters),
            'accessPeriods' => $this->accessPeriodRows($filters),
            'filters' => [
                'clients' => $this->clientOptions(),
                'projects' => $this->projectOptions(),
                'users' => $this->userOptions(),
                'downloadStatuses' => $this->downloadStatusOptions(),
                'rightsStatuses' => $this->rightsStatusOptions(),
                'extensionStatuses' => $this->extensionStatusOptions(),
                'accessStatuses' => $this->accessStatusOptions(),
            ],
            'activeFilters' => [
                'search' => $filters['search'],
                'clientId' => $filters['clientId'] ? (string) $filters['clientId'] : '',
                'projectId' => $filters['projectId'] ? (string) $filters['projectId'] : '',
                'userId' => $filters['userId'] ? (string) $filters['userId'] : '',
                'status' => $filters['status'],
                'dateFrom' => $filters['dateFrom'],
                'dateTo' => $filters['dateTo'],
            ],
            'limits' => [
                'maxRows' => self::MAX_ROWS,
            ],
        ]);
    }

    /**
     * @return array{search: string, clientId: int|null, projectId: int|null, userId: int|null, status: string, dateFrom: string, dateTo: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'clientId' => $request->query('client_id') ? max(1, (int) $request->query('client_id')) : null,
            'projectId' => $request->query('project_id') ? max(1, (int) $request->query('project_id')) : null,
            'userId' => $request->query('user_id') ? max(1, (int) $request->query('user_id')) : null,
            'status' => trim((string) $request->query('status', '')),
            'dateFrom' => $this->dateFilter($request->query('date_from')),
            'dateTo' => $this->dateFilter($request->query('date_to')),
        ];
    }

    private function summary(): array
    {
        $now = now();
        $soon = $now->copy()->addDays(30);

        return [
            'downloadsLast30Days' => DownloadJob::query()
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->count(),
            'downloadedImagesLast30Days' => (int) DownloadJob::query()
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->sum('image_count'),
            'openExtensionRequests' => ImageRightsExtensionRequest::query()
                ->whereIn('status', [
                    ImageRightsExtensionRequest::STATUS_REQUESTED,
                    ImageRightsExtensionRequest::STATUS_IN_PROGRESS,
                ])
                ->count(),
            'expiredRights' => Image::query()
                ->whereNotNull('rights_ends_at')
                ->whereDate('rights_ends_at', '<', $now)
                ->count(),
            'expiringRights' => Image::query()
                ->whereNotNull('rights_ends_at')
                ->whereDate('rights_ends_at', '>=', $now)
                ->whereDate('rights_ends_at', '<=', $soon)
                ->count(),
            'activeAccessPeriods' => ProjectAccessPeriod::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->count(),
            'expiringAccessPeriods' => ProjectAccessPeriod::query()
                ->where('is_active', true)
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [$now, $soon])
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function downloadRows(array $filters): array
    {
        $jobs = DownloadJob::query()
            ->with(['client:id,name', 'user:id,name,email'])
            ->tap(fn (Builder $query) => $this->applyDateFilters($query, $filters, 'created_at'))
            ->when($filters['userId'] !== null, fn (Builder $query) => $query->where('user_id', $filters['userId']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['search'] !== '', fn (Builder $query) => $this->applyDownloadSearch($query, $filters['search']))
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (DownloadJob $job) => $this->downloadRow($job))
            ->filter(fn (array $row) => $this->rowMatchesClientAndProject($row, $filters))
            ->values();

        return [
            'items' => $jobs->take(self::MAX_ROWS)->values(),
            'total' => $jobs->count(),
        ];
    }

    private function downloadRow(DownloadJob $job): array
    {
        $images = $this->imagesForDownload($job);
        $clients = $images->pluck('client.name')->filter()->unique()->values();
        $projects = $images->pluck('project.name')->filter()->unique()->values();

        return [
            'id' => $job->id,
            'createdAt' => $job->created_at?->toIso8601String(),
            'processedAt' => $job->processed_at?->toIso8601String(),
            'expiresAt' => $job->download_url_expires_at?->toIso8601String(),
            'title' => $job->title,
            'status' => $job->status,
            'statusLabel' => $this->downloadStatusLabel($job->status),
            'format' => $this->downloadFormatLabel($job),
            'imageCount' => $job->image_count,
            'actorName' => $job->user?->name ?: $job->user?->email,
            'actorId' => $job->user_id,
            'clientName' => $job->client?->name ?: $this->summaryList($clients, 'Multi-entreprises'),
            'clientIds' => $images->pluck('client_id')->unique()->values(),
            'projectName' => $this->summaryList($projects, 'Multi-projets'),
            'projectIds' => $images->pluck('project_id')->unique()->values(),
            'images' => $images->map(fn (Image $image) => $this->imageChip($image))->values(),
            'skippedImages' => $this->skippedImages($job),
            'errorDetails' => $job->error_details,
            'targetUrl' => route('downloads.index'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rightsRows(array $filters): array
    {
        $query = Image::query()
            ->with([
                'client:id,name',
                'project:id,name',
                'latestRightsExtensionRequest.requester:id,name,email',
                'latestRightsExtensionRequest.resolver:id,name,email',
            ])
            ->tap(fn (Builder $query) => $this->applyDateFilters($query, $filters, 'rights_ends_at'))
            ->when($filters['clientId'] !== null, fn (Builder $query) => $query->where('client_id', $filters['clientId']))
            ->when($filters['projectId'] !== null, fn (Builder $query) => $query->where('project_id', $filters['projectId']))
            ->when($filters['search'] !== '', fn (Builder $query) => $this->applyImageSearch($query, $filters['search']));

        $images = $query
            ->orderByRaw('rights_ends_at is null')
            ->orderBy('rights_ends_at')
            ->limit(500)
            ->get()
            ->map(fn (Image $image) => $this->rightsRow($image))
            ->filter(fn (array $row) => $filters['status'] === '' || $row['status'] === $filters['status'])
            ->values();

        return [
            'items' => $images->take(self::MAX_ROWS)->values(),
            'total' => $images->count(),
        ];
    }

    private function rightsRow(Image $image): array
    {
        $request = $image->latestRightsExtensionRequest;

        return [
            'id' => $image->id,
            'title' => $image->title,
            'clientId' => $image->client_id,
            'clientName' => $image->client?->name,
            'projectId' => $image->project_id,
            'projectName' => $image->project?->name,
            'startsAt' => $image->rights_starts_at?->toDateString(),
            'endsAt' => $image->rights_ends_at?->toDateString(),
            'status' => $image->rightsStatus(),
            'statusLabel' => $this->rightsStatusLabel($image->rightsStatus()),
            'latestRequest' => $request instanceof ImageRightsExtensionRequest ? [
                'id' => $request->id,
                'status' => $request->status,
                'statusLabel' => $request->statusLabel(),
                'requestedBy' => $request->requester?->name ?: $request->requester?->email,
                'resolvedBy' => $request->resolver?->name ?: $request->resolver?->email,
                'createdAt' => $request->created_at?->toIso8601String(),
                'resolvedAt' => $request->resolved_at?->toIso8601String(),
                'extendedRightsEndsAt' => $request->metadata['extended_rights_ends_at'] ?? null,
            ] : null,
            'targetUrl' => route('images.index', ['search' => $image->title]),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function extensionRequestRows(array $filters): array
    {
        $requests = ImageRightsExtensionRequest::query()
            ->with([
                'image:id,title',
                'client:id,name',
                'project:id,name',
                'requester:id,name,email',
                'resolver:id,name,email',
            ])
            ->tap(fn (Builder $query) => $this->applyDateFilters($query, $filters, 'created_at'))
            ->when($filters['clientId'] !== null, fn (Builder $query) => $query->where('client_id', $filters['clientId']))
            ->when($filters['projectId'] !== null, fn (Builder $query) => $query->where('project_id', $filters['projectId']))
            ->when($filters['userId'] !== null, fn (Builder $query) => $query->where('requested_by', $filters['userId']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['search'] !== '', fn (Builder $query) => $this->applyExtensionSearch($query, $filters['search']))
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (ImageRightsExtensionRequest $request) => [
                'id' => $request->id,
                'createdAt' => $request->created_at?->toIso8601String(),
                'resolvedAt' => $request->resolved_at?->toIso8601String(),
                'imageTitle' => $request->image?->title,
                'imageId' => $request->image_id,
                'clientName' => $request->client?->name,
                'clientId' => $request->client_id,
                'projectName' => $request->project?->name,
                'projectId' => $request->project_id,
                'requestedBy' => $request->requester?->name ?: $request->requester?->email,
                'resolvedBy' => $request->resolver?->name ?: $request->resolver?->email,
                'status' => $request->status,
                'statusLabel' => $request->statusLabel(),
                'rightsEndsAt' => $request->rights_ends_at?->toDateString(),
                'extendedRightsEndsAt' => $request->metadata['extended_rights_ends_at'] ?? null,
                'targetUrl' => route('images.index', ['search' => $request->image?->title]),
            ])
            ->values();

        return [
            'items' => $requests->take(self::MAX_ROWS)->values(),
            'total' => $requests->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function accessPeriodRows(array $filters): array
    {
        $periods = ProjectAccessPeriod::query()
            ->with(['client:id,name', 'project:id,name', 'project.images:id,project_id'])
            ->tap(fn (Builder $query) => $this->applyDateFilters($query, $filters, 'created_at'))
            ->when($filters['clientId'] !== null, fn (Builder $query) => $query->where('client_id', $filters['clientId']))
            ->when($filters['projectId'] !== null, fn (Builder $query) => $query->where('project_id', $filters['projectId']))
            ->when($filters['search'] !== '', fn (Builder $query) => $this->applyAccessSearch($query, $filters['search']))
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (ProjectAccessPeriod $period) => $this->accessPeriodRow($period))
            ->filter(fn (array $row) => $filters['status'] === '' || $row['status'] === $filters['status'])
            ->values();

        return [
            'items' => $periods->take(self::MAX_ROWS)->values(),
            'total' => $periods->count(),
        ];
    }

    private function accessPeriodRow(ProjectAccessPeriod $period): array
    {
        $status = $this->accessPeriodStatus($period);

        return [
            'id' => $period->id,
            'clientId' => $period->client_id,
            'clientName' => $period->client?->name,
            'projectId' => $period->project_id,
            'projectName' => $period->project?->name,
            'startsAt' => $period->starts_at?->toDateString(),
            'endsAt' => $period->ends_at?->toDateString(),
            'isActive' => $period->is_active,
            'status' => $status,
            'statusLabel' => $this->accessStatusLabel($status),
            'imagesCount' => $period->project?->images->count() ?? 0,
            'createdAt' => $period->created_at?->toIso8601String(),
            'updatedAt' => $period->updated_at?->toIso8601String(),
            'targetUrl' => route('access-periods.index'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rowMatchesClientAndProject(array $row, array $filters): bool
    {
        if ($filters['clientId'] !== null && ! collect($row['clientIds'])->contains($filters['clientId'])) {
            return false;
        }

        if ($filters['projectId'] !== null && ! collect($row['projectIds'])->contains($filters['projectId'])) {
            return false;
        }

        return true;
    }

    private function imagesForDownload(DownloadJob $job): Collection
    {
        $ids = collect($job->payload['requested_image_ids'] ?? [])
            ->map(fn ($imageId) => (int) $imageId)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Image::query()
            ->with(['client:id,name', 'project:id,name'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Image $image) => $ids->search($image->id))
            ->values();
    }

    private function imageChip(Image $image): array
    {
        return [
            'id' => $image->id,
            'title' => $image->title,
            'clientName' => $image->client?->name,
            'clientId' => $image->client_id,
            'projectName' => $image->project?->name,
            'projectId' => $image->project_id,
            'rightsEndsAt' => $image->rights_ends_at?->toDateString(),
            'rightsStatus' => $image->rightsStatus(),
        ];
    }

    private function skippedImages(DownloadJob $job): array
    {
        return collect($job->payload['skipped_images'] ?? [])
            ->filter(fn ($image) => is_array($image))
            ->map(fn (array $image) => [
                'id' => (int) ($image['id'] ?? 0),
                'title' => trim((string) ($image['title'] ?? 'Image sans titre')),
            ])
            ->values()
            ->all();
    }

    private function applyDateFilters(Builder $query, array $filters, string $dateColumn): void
    {
        $query
            ->when($filters['dateFrom'] !== '', fn (Builder $query) => $query->whereDate($dateColumn, '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn (Builder $query) => $query->whereDate($dateColumn, '<=', $filters['dateTo']));
    }

    private function applyDownloadSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('error_details', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$search}%"));
        });
    }

    private function applyImageSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$search}%"))
                ->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', "%{$search}%"));
        });
    }

    private function applyExtensionSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query
                ->orWhereHas('image', fn (Builder $image) => $image->where('title', 'like', "%{$search}%"))
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$search}%"))
                ->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', "%{$search}%"))
                ->orWhereHas('requester', fn (Builder $user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
        });
    }

    private function applyAccessSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$search}%"))
                ->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', "%{$search}%"));
        });
    }

    private function clientOptions(): Collection
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ]);
    }

    private function projectOptions(): Collection
    {
        return Project::query()
            ->with('client:id,name')
            ->orderBy('name')
            ->get(['id', 'client_id', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'clientId' => $project->client_id,
                'clientName' => $project->client?->name,
                'name' => $project->name,
            ]);
    }

    private function userOptions(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    private function downloadStatusOptions(): array
    {
        return [
            ['value' => 'pending', 'label' => 'En attente'],
            ['value' => 'processing', 'label' => 'Traitement'],
            ['value' => 'ready', 'label' => 'Prêt'],
            ['value' => 'failed', 'label' => 'Échec'],
        ];
    }

    private function rightsStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'expiring_soon', 'label' => 'Expire bientôt'],
            ['value' => 'expired', 'label' => 'Expirée'],
            ['value' => 'unlimited', 'label' => 'Illimitée'],
        ];
    }

    private function extensionStatusOptions(): array
    {
        return collect(ImageRightsExtensionRequest::STATUSES)
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => match ($status) {
                    ImageRightsExtensionRequest::STATUS_IN_PROGRESS => 'En cours',
                    ImageRightsExtensionRequest::STATUS_ACCEPTED => 'Acceptée',
                    ImageRightsExtensionRequest::STATUS_REFUSED => 'Refusée',
                    default => 'Demandée',
                },
            ])
            ->all();
    }

    private function accessStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Actif'],
            ['value' => 'upcoming', 'label' => 'À venir'],
            ['value' => 'expired', 'label' => 'Expiré'],
            ['value' => 'inactive', 'label' => 'Désactivé'],
        ];
    }

    private function downloadStatusLabel(string $status): string
    {
        return match ($status) {
            'ready' => 'Prêt',
            'failed' => 'Échec',
            'processing' => 'Traitement',
            default => 'En attente',
        };
    }

    private function downloadFormatLabel(DownloadJob $job): string
    {
        $variant = (string) ($job->payload['variant'] ?? '');

        if ($variant === 'crop') {
            return 'Export '.($job->payload['crop_preset'] ?? 'recadré');
        }

        if ($job->is_hd || $variant === 'hd') {
            return 'HD impression';
        }

        return 'Web réseaux sociaux';
    }

    private function rightsStatusLabel(string $status): string
    {
        return match ($status) {
            'expired' => 'Cession expirée',
            'expiring_soon' => 'Expire bientôt',
            'unlimited' => 'Illimitée',
            default => 'Active',
        };
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

    private function accessStatusLabel(string $status): string
    {
        return match ($status) {
            'upcoming' => 'À venir',
            'expired' => 'Expiré',
            'inactive' => 'Désactivé',
            default => 'Actif',
        };
    }

    private function summaryList(Collection $values, string $multipleLabel): ?string
    {
        if ($values->isEmpty()) {
            return null;
        }

        return $values->count() === 1 ? $values->first() : $multipleLabel;
    }

    private function dateFilter(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
