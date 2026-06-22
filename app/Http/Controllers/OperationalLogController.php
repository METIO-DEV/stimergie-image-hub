<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\SharedAlbum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OperationalLogController extends Controller
{
    private const EVENT_TYPES = [
        'cession',
        'demande_extension',
        'droits_acces',
        'telechargement',
        'partage',
        'audit',
    ];

    private const PER_SOURCE_LIMIT = 80;

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canViewOperationalLogs($user), 403);

        $clientIds = $this->manageableClientIds($user);
        $filters = $this->filters($request);
        $events = $this->events($user, $clientIds, $filters);

        return Inertia::render('Operations/Index', [
            'events' => $events
                ->sortByDesc('date')
                ->values()
                ->take(120)
                ->all(),
            'stats' => $this->stats($user, $clientIds),
            'filters' => [
                'clients' => $this->clientOptions($clientIds),
                'projects' => $this->projectOptions($user, $clientIds),
                'types' => $this->typeOptions(),
                'statuses' => $this->statusOptions(),
            ],
            'activeFilters' => [
                'search' => $filters['search'],
                'clientId' => $filters['clientId'] ? (string) $filters['clientId'] : '',
                'projectId' => $filters['projectId'] ? (string) $filters['projectId'] : '',
                'type' => $filters['type'],
                'status' => $filters['status'],
                'dateFrom' => $filters['dateFrom'],
                'dateTo' => $filters['dateTo'],
            ],
            'canViewSensitiveAuditData' => $user->isSuperAdmin(),
        ]);
    }

    /**
     * @return array{search: string, clientId: int|null, projectId: int|null, type: string, status: string, dateFrom: string, dateTo: string}
     */
    private function filters(Request $request): array
    {
        $type = (string) $request->query('type', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'clientId' => $request->query('client_id') ? max(1, (int) $request->query('client_id')) : null,
            'projectId' => $request->query('project_id') ? max(1, (int) $request->query('project_id')) : null,
            'type' => in_array($type, self::EVENT_TYPES, true) ? $type : '',
            'status' => trim((string) $request->query('status', '')),
            'dateFrom' => $this->dateFilter($request->query('date_from')),
            'dateTo' => $this->dateFilter($request->query('date_to')),
        ];
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array{search: string, clientId: int|null, projectId: int|null, type: string, status: string, dateFrom: string, dateTo: string}  $filters
     */
    private function events(User $user, ?array $clientIds, array $filters): Collection
    {
        $events = collect();

        if ($this->shouldLoadType($filters, 'cession')) {
            $events = $events->merge($this->cessionEvents($clientIds, $filters));
        }

        if ($this->shouldLoadType($filters, 'demande_extension')) {
            $events = $events->merge($this->rightsExtensionRequestEvents($clientIds, $filters));
        }

        if ($this->shouldLoadType($filters, 'droits_acces')) {
            $events = $events->merge($this->accessPeriodEvents($clientIds, $filters));
        }

        if ($this->shouldLoadType($filters, 'telechargement')) {
            $events = $events->merge($this->downloadEvents($user, $clientIds, $filters));
        }

        if ($this->shouldLoadType($filters, 'partage')) {
            $events = $events->merge($this->sharedAlbumEvents($user, $clientIds, $filters));
        }

        if ($this->shouldLoadType($filters, 'audit')) {
            $events = $events->merge($this->auditEvents($user, $clientIds, $filters));
        }

        if ($filters['status'] !== '') {
            $events = $events->filter(fn (array $event) => $event['status'] === $filters['status']);
        }

        return $events;
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function cessionEvents(?array $clientIds, array $filters): Collection
    {
        return Image::query()
            ->with(['client:id,name', 'project:id,name'])
            ->whereNotNull('rights_ends_at')
            ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'rights_ends_at'))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search'], [
                'title',
                'description',
            ]))
            ->orderByDesc('rights_ends_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (Image $image) => [
                'id' => "cession-{$image->id}",
                'sourceId' => $image->id,
                'type' => 'cession',
                'typeLabel' => 'Cession',
                'date' => $image->rights_ends_at?->toIso8601String(),
                'status' => $image->rightsStatus(),
                'statusLabel' => $this->rightsStatusLabel($image->rightsStatus()),
                'title' => $image->title,
                'description' => 'Fin de cession des droits image.',
                'clientName' => $image->client?->name,
                'projectName' => $image->project?->name,
                'imageTitle' => $image->title,
                'actorName' => null,
                'targetUrl' => route('images.index', ['search' => $image->title]),
                'metadata' => [
                    'rightsStartsAt' => $image->rights_starts_at?->toDateString(),
                    'rightsEndsAt' => $image->rights_ends_at?->toDateString(),
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function rightsExtensionRequestEvents(?array $clientIds, array $filters): Collection
    {
        return ImageRightsExtensionRequest::query()
            ->with([
                'image:id,title',
                'client:id,name',
                'project:id,name',
                'requester:id,name,email',
                'resolver:id,name,email',
            ])
            ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search']))
            ->latest()
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (ImageRightsExtensionRequest $rightsRequest) => [
                'id' => "demande_extension-{$rightsRequest->id}",
                'sourceId' => $rightsRequest->id,
                'type' => 'demande_extension',
                'typeLabel' => "Demande d'extension",
                'date' => $rightsRequest->created_at?->toIso8601String(),
                'status' => $rightsRequest->status,
                'statusLabel' => $rightsRequest->statusLabel(),
                'title' => $rightsRequest->image?->title ?: "Demande #{$rightsRequest->id}",
                'description' => 'Demande de prolongation de cession de droits.',
                'clientName' => $rightsRequest->client?->name,
                'projectName' => $rightsRequest->project?->name,
                'imageTitle' => $rightsRequest->image?->title,
                'actorName' => $rightsRequest->requester?->name ?: $rightsRequest->requester?->email,
                'targetUrl' => route('images.index', ['search' => $rightsRequest->image?->title]),
                'metadata' => [
                    'rightsEndsAt' => $rightsRequest->rights_ends_at?->toDateString(),
                    'resolvedAt' => $rightsRequest->resolved_at?->toIso8601String(),
                    'resolvedBy' => $rightsRequest->resolver?->name ?: $rightsRequest->resolver?->email,
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function accessPeriodEvents(?array $clientIds, array $filters): Collection
    {
        return ProjectAccessPeriod::query()
            ->with(['client:id,name', 'project:id,name'])
            ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'ends_at'))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search']))
            ->orderByDesc('ends_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (ProjectAccessPeriod $period) => [
                'id' => "droits_acces-{$period->id}",
                'sourceId' => $period->id,
                'type' => 'droits_acces',
                'typeLabel' => "Droits d'accès",
                'date' => ($period->ends_at ?: $period->starts_at ?: $period->created_at)?->toIso8601String(),
                'status' => $this->accessPeriodStatus($period),
                'statusLabel' => $this->accessPeriodStatusLabel($this->accessPeriodStatus($period)),
                'title' => $period->project?->name ?: "Accès #{$period->id}",
                'description' => "Période d'accès client au projet.",
                'clientName' => $period->client?->name,
                'projectName' => $period->project?->name,
                'imageTitle' => null,
                'actorName' => null,
                'targetUrl' => route('access-periods.index'),
                'metadata' => [
                    'startsAt' => $period->starts_at?->toDateString(),
                    'endsAt' => $period->ends_at?->toDateString(),
                    'isActive' => $period->is_active,
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function downloadEvents(User $user, ?array $clientIds, array $filters): Collection
    {
        if ($filters['projectId'] !== null) {
            return collect();
        }

        return DownloadJob::query()
            ->with(['client:id,name', 'user:id,name,email'])
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where('user_id', $user->id))
            ->when($clientIds !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($clientIds): void {
                $query->whereNull('client_id')->orWhereIn('client_id', $clientIds);
            }))
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'created_at', false))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search'], ['title']))
            ->latest()
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (DownloadJob $job) => [
                'id' => "telechargement-{$job->id}",
                'sourceId' => $job->id,
                'type' => 'telechargement',
                'typeLabel' => 'Téléchargement',
                'date' => $job->created_at?->toIso8601String(),
                'status' => $job->status,
                'statusLabel' => $this->downloadStatusLabel($job->status),
                'title' => $job->title,
                'description' => "{$job->image_count} image".($job->image_count > 1 ? 's' : '').($job->is_hd ? ' en HD' : ''),
                'clientName' => $job->client?->name,
                'projectName' => null,
                'imageTitle' => null,
                'actorName' => $job->user?->name ?: $job->user?->email,
                'targetUrl' => route('downloads.index'),
                'metadata' => [
                    'imageCount' => $job->image_count,
                    'isHd' => $job->is_hd,
                    'processedAt' => $job->processed_at?->toIso8601String(),
                    'expiresAt' => $job->download_url_expires_at?->toIso8601String(),
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function sharedAlbumEvents(User $user, ?array $clientIds, array $filters): Collection
    {
        if ($filters['projectId'] !== null) {
            return collect();
        }

        return SharedAlbum::query()
            ->with(['client:id,name', 'creator:id,name,email'])
            ->withCount('images')
            ->when($clientIds !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($clientIds, $user): void {
                $query->whereIn('client_id', $clientIds)
                    ->orWhere('created_by', $user->id);
            }))
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'expires_at', false))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search'], ['name', 'description']))
            ->orderByDesc('expires_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (SharedAlbum $album) => [
                'id' => "partage-{$album->id}",
                'sourceId' => $album->id,
                'type' => 'partage',
                'typeLabel' => 'Lien partagé',
                'date' => ($album->expires_at ?: $album->created_at)?->toIso8601String(),
                'status' => $this->sharedAlbumStatus($album),
                'statusLabel' => $this->sharedAlbumStatusLabel($this->sharedAlbumStatus($album)),
                'title' => $album->name,
                'description' => "{$album->images_count} image".($album->images_count > 1 ? 's' : '').' partagée(s).',
                'clientName' => $album->client?->name,
                'projectName' => null,
                'imageTitle' => null,
                'actorName' => $album->creator?->name ?: $album->creator?->email,
                'targetUrl' => route('gallery.index'),
                'metadata' => [
                    'startsAt' => $album->starts_at?->toDateString(),
                    'expiresAt' => $album->expires_at?->toDateString(),
                    'isActive' => $album->is_active,
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function auditEvents(User $user, ?array $clientIds, array $filters): Collection
    {
        if ($filters['projectId'] !== null) {
            return collect();
        }

        return AuditLog::query()
            ->with(['actor:id,name,email', 'client:id,name'])
            ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'created_at', false))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search'], ['action']))
            ->latest()
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => "audit-{$log->id}",
                'sourceId' => $log->id,
                'type' => 'audit',
                'typeLabel' => 'Audit',
                'date' => $log->created_at?->toIso8601String(),
                'status' => 'trace',
                'statusLabel' => 'Trace',
                'title' => $log->action,
                'description' => $this->auditSubjectLabel($log),
                'clientName' => $log->client?->name,
                'projectName' => null,
                'imageTitle' => null,
                'actorName' => $log->actor?->name ?: $log->actor?->email,
                'targetUrl' => null,
                'metadata' => [
                    'subjectType' => $log->subject_type,
                    'subjectId' => $log->subject_id,
                    ...$this->sensitiveAuditMetadata($user, $log),
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     */
    private function stats(User $user, ?array $clientIds): array
    {
        return [
            'rightsExpired' => Image::query()
                ->whereNotNull('rights_ends_at')
                ->whereDate('rights_ends_at', '<', today())
                ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
                ->count(),
            'rightsExpiringSoon' => Image::query()
                ->whereBetween('rights_ends_at', [today(), today()->addDays(30)])
                ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
                ->count(),
            'openRightsRequests' => ImageRightsExtensionRequest::query()
                ->whereIn('status', [
                    ImageRightsExtensionRequest::STATUS_REQUESTED,
                    ImageRightsExtensionRequest::STATUS_IN_PROGRESS,
                ])
                ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
                ->count(),
            'accessEndingSoon' => ProjectAccessPeriod::query()
                ->where('is_active', true)
                ->whereBetween('ends_at', [now(), now()->addDays(30)])
                ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
                ->count(),
            'failedDownloads' => DownloadJob::query()
                ->where('status', 'failed')
                ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where('user_id', $user->id))
                ->when($clientIds !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($clientIds): void {
                    $query->whereNull('client_id')->orWhereIn('client_id', $clientIds);
                }))
                ->count(),
            'activeSharedAlbums' => SharedAlbum::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->when($clientIds !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($clientIds, $user): void {
                    $query->whereIn('client_id', $clientIds)
                        ->orWhere('created_by', $user->id);
                }))
                ->count(),
        ];
    }

    private function canViewOperationalLogs(User $user): bool
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

    /**
     * @param  array<int>|null  $clientIds
     */
    private function applyClientScope(Builder $query, ?array $clientIds): void
    {
        if ($clientIds === null) {
            return;
        }

        $query->whereIn('client_id', $clientIds);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(Builder $query, array $filters, string $dateColumn, bool $hasProject = true): void
    {
        $query
            ->when($filters['clientId'] !== null, fn (Builder $query) => $query->where('client_id', $filters['clientId']))
            ->when($hasProject && $filters['projectId'] !== null, fn (Builder $query) => $query->where('project_id', $filters['projectId']))
            ->when($filters['dateFrom'] !== '', fn (Builder $query) => $query->whereDate($dateColumn, '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn (Builder $query) => $query->whereDate($dateColumn, '<=', $filters['dateTo']));
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, string $search, array $columns = []): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search, $columns): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }

            if (method_exists($query->getModel(), 'client')) {
                $query->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$search}%"));
            }

            if (method_exists($query->getModel(), 'project')) {
                $query->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', "%{$search}%"));
            }
        });
    }

    private function shouldLoadType(array $filters, string $type): bool
    {
        return $filters['type'] === '' || $filters['type'] === $type;
    }

    private function dateFilter(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * @param  array<int>|null  $clientIds
     */
    private function clientOptions(?array $clientIds): Collection
    {
        return Client::query()
            ->when($clientIds !== null, fn (Builder $query) => $query->whereIn('id', $clientIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     */
    private function projectOptions(User $user, ?array $clientIds): Collection
    {
        return Project::query()
            ->with('client:id,name')
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereIn('client_id', $clientIds ?? []))
            ->orderBy('name')
            ->get(['id', 'client_id', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'clientId' => $project->client_id,
                'name' => $project->name,
                'clientName' => $project->client?->name,
            ]);
    }

    private function typeOptions(): array
    {
        return [
            ['value' => 'cession', 'label' => 'Cessions'],
            ['value' => 'demande_extension', 'label' => "Demandes d'extension"],
            ['value' => 'droits_acces', 'label' => "Droits d'accès"],
            ['value' => 'telechargement', 'label' => 'Téléchargements'],
            ['value' => 'partage', 'label' => 'Liens partagés'],
            ['value' => 'audit', 'label' => 'Audit'],
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'expired', 'label' => 'Expiré'],
            ['value' => 'expiring_soon', 'label' => 'À échéance'],
            ['value' => 'active', 'label' => 'Actif'],
            ['value' => 'unlimited', 'label' => 'Sans limite'],
            ['value' => 'demande', 'label' => 'Demandée'],
            ['value' => 'en_cours', 'label' => 'En cours'],
            ['value' => 'accepte', 'label' => 'Acceptée'],
            ['value' => 'refuse', 'label' => 'Refusée'],
            ['value' => 'ready', 'label' => 'Prêt'],
            ['value' => 'pending', 'label' => 'En attente'],
            ['value' => 'processing', 'label' => 'Traitement'],
            ['value' => 'failed', 'label' => 'Échec'],
            ['value' => 'inactive', 'label' => 'Inactif'],
            ['value' => 'upcoming', 'label' => 'À venir'],
            ['value' => 'trace', 'label' => 'Trace'],
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

    private function accessPeriodStatusLabel(string $status): string
    {
        return match ($status) {
            'inactive' => 'Inactif',
            'upcoming' => 'À venir',
            'expired' => 'Expiré',
            default => 'Actif',
        };
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

    private function sharedAlbumStatus(SharedAlbum $album): string
    {
        if (! $album->is_active) {
            return 'inactive';
        }

        if ($album->starts_at && $album->starts_at->isFuture()) {
            return 'upcoming';
        }

        if ($album->expires_at && $album->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    private function sharedAlbumStatusLabel(string $status): string
    {
        return match ($status) {
            'inactive' => 'Inactif',
            'upcoming' => 'À venir',
            'expired' => 'Expiré',
            default => 'Actif',
        };
    }

    private function auditSubjectLabel(AuditLog $log): string
    {
        if (! $log->subject_type) {
            return 'Action enregistrée dans le journal.';
        }

        $subject = class_basename($log->subject_type);

        return $log->subject_id ? "{$subject} #{$log->subject_id}" : $subject;
    }

    private function sensitiveAuditMetadata(User $user, AuditLog $log): array
    {
        if (! $user->isSuperAdmin()) {
            return [];
        }

        return [
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
        ];
    }
}
