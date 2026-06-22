<?php

namespace App\Http\Controllers;

use App\Models\AssetTransferJob;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Import;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperationalLogController extends Controller
{
    private const EVENT_TYPES = [
        'audit',
        'telechargement',
        'import',
        'transfert',
        'job',
        'demande_extension',
    ];

    private const EVENT_VIEWS = [
        'audit_traces',
        'open_rights_requests',
        'failed_downloads',
        'failed_imports',
        'failed_transfers',
        'failed_jobs',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canViewOperationalLogs($user), 403);

        $clientIds = $this->manageableClientIds($user);
        $filters = $this->filters($request);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(10, (int) $request->integer('per_page', 25)));
        $events = $this->paginatedEvents($user, $clientIds, $filters, $page, $perPage);

        return Inertia::render('Operations/Index', [
            'events' => $events->items(),
            'pagination' => [
                'currentPage' => $events->currentPage(),
                'perPage' => $events->perPage(),
                'total' => $events->total(),
                'lastPage' => $events->lastPage(),
            ],
            'stats' => $this->stats($user, $clientIds),
            'filters' => [
                'clients' => $this->clientOptions($clientIds),
                'projects' => $this->projectOptions($user, $clientIds),
                'types' => $this->typeOptions($user),
                'statuses' => $this->statusOptions(),
            ],
            'activeFilters' => [
                'search' => $filters['search'],
                'clientId' => $filters['clientId'] ? (string) $filters['clientId'] : '',
                'projectId' => $filters['projectId'] ? (string) $filters['projectId'] : '',
                'type' => $filters['type'],
                'status' => $filters['status'],
                'view' => $filters['view'],
                'dateFrom' => $filters['dateFrom'],
                'dateTo' => $filters['dateTo'],
                'perPage' => (string) $perPage,
            ],
            'canViewSensitiveAuditData' => $user->isSuperAdmin(),
        ]);
    }

    /**
     * @return array{search: string, clientId: int|null, projectId: int|null, type: string, status: string, view: string, dateFrom: string, dateTo: string}
     */
    private function filters(Request $request): array
    {
        $type = (string) $request->query('type', '');
        $view = (string) $request->query('view', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'clientId' => $request->query('client_id') ? max(1, (int) $request->query('client_id')) : null,
            'projectId' => $request->query('project_id') ? max(1, (int) $request->query('project_id')) : null,
            'type' => in_array($type, self::EVENT_TYPES, true) ? $type : '',
            'status' => trim((string) $request->query('status', '')),
            'view' => in_array($view, self::EVENT_VIEWS, true) ? $view : '',
            'dateFrom' => $this->dateFilter($request->query('date_from')),
            'dateTo' => $this->dateFilter($request->query('date_to')),
        ];
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array{search: string, clientId: int|null, projectId: int|null, type: string, status: string, view: string, dateFrom: string, dateTo: string}  $filters
     */
    private function paginatedEvents(User $user, ?array $clientIds, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $events = $filters['view'] !== ''
            ? $this->eventsForView($user, $clientIds, $filters)
            : collect()
                ->when($this->shouldLoadType($filters, 'audit'), fn (Collection $events) => $events->merge($this->auditEvents($user, $clientIds, $filters)))
                ->when($this->shouldLoadType($filters, 'telechargement'), fn (Collection $events) => $events->merge($this->downloadEvents($user, $clientIds, $filters)))
                ->when($this->shouldLoadType($filters, 'import'), fn (Collection $events) => $events->merge($this->importEvents($clientIds, $filters)))
                ->when($user->isSuperAdmin() && $this->shouldLoadType($filters, 'transfert'), fn (Collection $events) => $events->merge($this->transferEvents($filters)))
                ->when($user->isSuperAdmin() && $this->shouldLoadType($filters, 'job'), fn (Collection $events) => $events->merge($this->failedJobEvents($filters)))
                ->when($this->shouldLoadType($filters, 'demande_extension'), fn (Collection $events) => $events->merge($this->rightsExtensionRequestEvents($clientIds, $filters)));

        if ($filters['status'] !== '') {
            $events = $events->filter(fn (array $event) => $event['status'] === $filters['status']);
        }

        $events = $events
            ->sortByDesc(fn (array $event) => $event['date'] ?: '')
            ->values();

        return new LengthAwarePaginator(
            $events->forPage($page, $perPage)->values()->all(),
            $events->count(),
            $perPage,
            $page,
            [
                'path' => route('operations.index'),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function eventsForView(User $user, ?array $clientIds, array $filters): Collection
    {
        return match ($filters['view']) {
            'audit_traces' => $this->auditEvents($user, $clientIds, $filters),
            'open_rights_requests' => $this->rightsExtensionRequestEvents($clientIds, $filters)
                ->filter(fn (array $event) => in_array($event['status'], [
                    ImageRightsExtensionRequest::STATUS_REQUESTED,
                    ImageRightsExtensionRequest::STATUS_IN_PROGRESS,
                ], true)),
            'failed_downloads' => $this->downloadEvents($user, $clientIds, $filters)
                ->filter(fn (array $event) => $event['status'] === 'failed'),
            'failed_imports' => $this->importEvents($clientIds, $filters)
                ->filter(fn (array $event) => $event['status'] === 'failed' || (int) ($event['metadata']['failedItems'] ?? 0) > 0),
            'failed_transfers' => $user->isSuperAdmin()
                ? $this->transferEvents($filters)
                    ->filter(fn (array $event) => $event['status'] === 'failed' || (int) ($event['metadata']['failedFolders'] ?? 0) > 0)
                : collect(),
            'failed_jobs' => $user->isSuperAdmin() ? $this->failedJobEvents($filters) : collect(),
            default => collect(),
        };
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
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => "audit-{$log->id}",
                'sourceId' => $log->id,
                'type' => 'audit',
                'typeLabel' => 'Audit applicatif',
                'date' => $log->created_at?->toIso8601String(),
                'status' => 'trace',
                'statusLabel' => 'Trace',
                'severity' => $this->auditSeverity($log),
                'title' => $log->action,
                'description' => $this->auditSubjectLabel($log),
                'clientName' => $log->client?->name,
                'projectName' => null,
                'actorName' => $log->actor?->name ?: $log->actor?->email,
                'targetUrl' => null,
                'metadata' => [
                    'subjectType' => $log->subject_type,
                    'subjectId' => $log->subject_id,
                    'properties' => $this->compactPayload($log->properties),
                    ...$this->sensitiveAuditMetadata($user, $log),
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
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search'], ['title', 'error_details']))
            ->latest()
            ->get()
            ->map(fn (DownloadJob $job) => [
                'id' => "telechargement-{$job->id}",
                'sourceId' => $job->id,
                'type' => 'telechargement',
                'typeLabel' => 'Téléchargement',
                'date' => $job->created_at?->toIso8601String(),
                'status' => $job->status,
                'statusLabel' => $this->downloadStatusLabel($job->status),
                'severity' => $job->status === 'failed' ? 'error' : ($job->status === 'ready' ? 'info' : 'warning'),
                'title' => $job->title,
                'description' => $job->error_details ?: "{$job->image_count} image".($job->image_count > 1 ? 's' : '').($job->is_hd ? ' en HD' : ''),
                'clientName' => $job->client?->name,
                'projectName' => null,
                'actorName' => $job->user?->name ?: $job->user?->email,
                'targetUrl' => route('downloads.index'),
                'metadata' => [
                    'imageCount' => $job->image_count,
                    'isHd' => $job->is_hd,
                    'format' => $this->downloadFormatLabel($job),
                    'requestedImages' => $this->downloadImageSummary($job),
                    'skippedImages' => $this->downloadSkippedImageSummary($job),
                    'variant' => $job->payload['variant'] ?? null,
                    'cropPreset' => $job->payload['crop_preset'] ?? null,
                    'cropSource' => $job->payload['crop_source'] ?? null,
                    'processedAt' => $job->processed_at?->toIso8601String(),
                    'expiresAt' => $job->download_url_expires_at?->toIso8601String(),
                    'errorDetails' => $job->error_details,
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     * @param  array<string, mixed>  $filters
     */
    private function importEvents(?array $clientIds, array $filters): Collection
    {
        return Import::query()
            ->with(['client:id,name', 'project:id,name', 'starter:id,name,email'])
            ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search'], ['source', 'status']))
            ->latest()
            ->get()
            ->map(fn (Import $import) => [
                'id' => "import-{$import->id}",
                'sourceId' => $import->id,
                'type' => 'import',
                'typeLabel' => 'Import images',
                'date' => ($import->finished_at ?: $import->started_at ?: $import->created_at)?->toIso8601String(),
                'status' => $import->status,
                'statusLabel' => $this->importStatusLabel($import->status),
                'severity' => $import->status === 'failed' || $import->failed_items > 0 ? 'error' : ($import->status === 'completed' ? 'info' : 'warning'),
                'title' => "Import #{$import->id}",
                'description' => "{$import->processed_items}/{$import->total_items} traité(s), {$import->failed_items} échec(s).",
                'clientName' => $import->client?->name,
                'projectName' => $import->project?->name,
                'actorName' => $import->starter?->name ?: $import->starter?->email,
                'targetUrl' => route('images.index', ['tab' => 'imports']),
                'metadata' => [
                    'source' => $import->source,
                    'totalItems' => $import->total_items,
                    'uploadedItems' => $import->uploaded_items,
                    'processedItems' => $import->processed_items,
                    'failedItems' => $import->failed_items,
                    'duplicateItems' => $import->duplicate_items,
                    'failedItemDetails' => $this->failedImportItemSummary($import),
                    'startedAt' => $import->started_at?->toIso8601String(),
                    'finishedAt' => $import->finished_at?->toIso8601String(),
                    'metadata' => $this->compactPayload($import->metadata),
                ],
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function transferEvents(array $filters): Collection
    {
        if ($filters['clientId'] !== null || $filters['projectId'] !== null) {
            return collect();
        }

        return AssetTransferJob::query()
            ->with('starter:id,name,email')
            ->tap(fn (Builder $query) => $this->applyCommonFilters($query, $filters, 'created_at', false))
            ->tap(fn (Builder $query) => $this->applySearch($query, $filters['search'], ['mode', 'current_folder', 'log_file']))
            ->latest()
            ->get()
            ->map(fn (AssetTransferJob $job) => [
                'id' => "transfert-{$job->id}",
                'sourceId' => $job->id,
                'type' => 'transfert',
                'typeLabel' => 'Transfert fichiers',
                'date' => ($job->finished_at ?: $job->started_at ?: $job->created_at)?->toIso8601String(),
                'status' => $job->status,
                'statusLabel' => $this->transferStatusLabel($job->status),
                'severity' => $job->status === 'failed' || $job->failed_folders > 0 ? 'error' : ($job->isActive() ? 'warning' : 'info'),
                'title' => "Transfert #{$job->id}",
                'description' => "{$job->processed_folders}/{$job->total_folders} dossier(s), {$job->failed_folders} échec(s).",
                'clientName' => null,
                'projectName' => null,
                'actorName' => $job->starter?->name ?: $job->starter?->email,
                'targetUrl' => route('asset-transfers.index'),
                'metadata' => [
                    'mode' => $job->mode,
                    'currentFolder' => $job->current_folder,
                    'totalFolders' => $job->total_folders,
                    'processedFolders' => $job->processed_folders,
                    'failedFolders' => $job->failed_folders,
                    'failedFolderDetails' => $this->compactPayload($job->failed_folder_details),
                    'logFile' => $job->log_file,
                    'startedAt' => $job->started_at?->toIso8601String(),
                    'finishedAt' => $job->finished_at?->toIso8601String(),
                ],
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function failedJobEvents(array $filters): Collection
    {
        if ($filters['clientId'] !== null || $filters['projectId'] !== null) {
            return collect();
        }

        $query = DB::table('failed_jobs');

        if ($filters['dateFrom'] !== '') {
            $query->whereDate('failed_at', '>=', $filters['dateFrom']);
        }

        if ($filters['dateTo'] !== '') {
            $query->whereDate('failed_at', '<=', $filters['dateTo']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('queue', 'like', "%{$search}%")
                    ->orWhere('connection', 'like', "%{$search}%")
                    ->orWhere('exception', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('failed_at')
            ->get()
            ->map(fn ($job) => [
                'id' => "job-{$job->id}",
                'sourceId' => $job->id,
                'type' => 'job',
                'typeLabel' => 'Job échoué',
                'date' => $job->failed_at,
                'status' => 'failed',
                'statusLabel' => 'Échec',
                'severity' => 'error',
                'title' => "Job {$job->queue}",
                'description' => $this->firstLine($job->exception),
                'clientName' => null,
                'projectName' => null,
                'actorName' => null,
                'targetUrl' => null,
                'metadata' => [
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'exception' => $this->compactText($job->exception, 1200),
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
            ->get()
            ->map(fn (ImageRightsExtensionRequest $rightsRequest) => [
                'id' => "demande_extension-{$rightsRequest->id}",
                'sourceId' => $rightsRequest->id,
                'type' => 'demande_extension',
                'typeLabel' => "Demande d'extension",
                'date' => $rightsRequest->created_at?->toIso8601String(),
                'status' => $rightsRequest->status,
                'statusLabel' => $rightsRequest->statusLabel(),
                'severity' => in_array($rightsRequest->status, [
                    ImageRightsExtensionRequest::STATUS_REQUESTED,
                    ImageRightsExtensionRequest::STATUS_IN_PROGRESS,
                ], true) ? 'warning' : 'info',
                'title' => $rightsRequest->image?->title ?: "Demande #{$rightsRequest->id}",
                'description' => 'Demande de prolongation de cession de droits.',
                'clientName' => $rightsRequest->client?->name,
                'projectName' => $rightsRequest->project?->name,
                'actorName' => $rightsRequest->requester?->name ?: $rightsRequest->requester?->email,
                'targetUrl' => route('images.index', ['search' => $rightsRequest->image?->title]),
                'metadata' => [
                    'rightsEndsAt' => $rightsRequest->rights_ends_at?->toDateString(),
                    'resolvedAt' => $rightsRequest->resolved_at?->toIso8601String(),
                    'resolvedBy' => $rightsRequest->resolver?->name ?: $rightsRequest->resolver?->email,
                    'requestMetadata' => $this->compactPayload($rightsRequest->metadata),
                ],
            ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     */
    private function stats(User $user, ?array $clientIds): array
    {
        return [
            'auditLogs' => AuditLog::query()
                ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
                ->count(),
            'openRightsRequests' => ImageRightsExtensionRequest::query()
                ->whereIn('status', [
                    ImageRightsExtensionRequest::STATUS_REQUESTED,
                    ImageRightsExtensionRequest::STATUS_IN_PROGRESS,
                ])
                ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
                ->count(),
            'failedDownloads' => DownloadJob::query()
                ->where('status', 'failed')
                ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where('user_id', $user->id))
                ->when($clientIds !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($clientIds): void {
                    $query->whereNull('client_id')->orWhereIn('client_id', $clientIds);
                }))
                ->count(),
            'failedImports' => Import::query()
                ->where(fn (Builder $query) => $query->where('status', 'failed')->orWhere('failed_items', '>', 0))
                ->tap(fn (Builder $query) => $this->applyClientScope($query, $clientIds))
                ->count(),
            'failedTransfers' => $user->isSuperAdmin()
                ? AssetTransferJob::query()
                    ->where(fn (Builder $query) => $query->where('status', 'failed')->orWhere('failed_folders', '>', 0))
                    ->count()
                : 0,
            'failedJobs' => $user->isSuperAdmin()
                ? DB::table('failed_jobs')->count()
                : 0,
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

    private function downloadImageSummary(DownloadJob $job): ?string
    {
        $ids = collect($job->payload['requested_image_ids'] ?? [])
            ->map(fn ($imageId) => (int) $imageId)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $images = Image::query()
            ->with(['client:id,name', 'project:id,name'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Image $image) => $ids->search($image->id))
            ->map(fn (Image $image) => trim(sprintf(
                '#%d %s%s%s',
                $image->id,
                $image->title,
                $image->client?->name ? " - {$image->client->name}" : '',
                $image->project?->name ? " / {$image->project->name}" : '',
            )))
            ->implode(' ; ');

        return $this->compactText($images, 800);
    }

    private function downloadSkippedImageSummary(DownloadJob $job): ?string
    {
        $skipped = collect($job->payload['skipped_images'] ?? [])
            ->map(function ($image): ?string {
                if (! is_array($image)) {
                    return null;
                }

                $title = trim((string) ($image['title'] ?? ''));
                $id = (int) ($image['id'] ?? 0);

                return trim(($id > 0 ? "#{$id} " : '').($title !== '' ? $title : 'Image sans titre'));
            })
            ->filter()
            ->implode(' ; ');

        return $this->compactText($skipped, 500);
    }

    private function failedImportItemSummary(Import $import): ?string
    {
        $items = $import->items()
            ->with('image:id,title')
            ->where('status', 'failed')
            ->latest()
            ->limit(8)
            ->get()
            ->map(function ($item): string {
                $label = $item->original_filename ?: $item->relative_path ?: $item->source_identifier;

                if ($item->image?->title) {
                    $label .= " ({$item->image->title})";
                }

                if ($item->error_details) {
                    $label .= ": {$item->error_details}";
                }

                return $label;
            })
            ->implode(' ; ');

        return $this->compactText($items, 800);
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

    private function typeOptions(User $user): array
    {
        $types = [
            ['value' => 'audit', 'label' => 'Audit applicatif'],
            ['value' => 'telechargement', 'label' => 'Téléchargements'],
            ['value' => 'import', 'label' => 'Imports'],
            ['value' => 'demande_extension', 'label' => "Demandes d'extension"],
        ];

        if ($user->isSuperAdmin()) {
            $types[] = ['value' => 'transfert', 'label' => 'Transferts fichiers'];
            $types[] = ['value' => 'job', 'label' => 'Jobs échoués'];
        }

        return $types;
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'trace', 'label' => 'Trace'],
            ['value' => 'ready', 'label' => 'Prêt'],
            ['value' => 'pending', 'label' => 'En attente'],
            ['value' => 'processing', 'label' => 'Traitement'],
            ['value' => 'running', 'label' => 'En cours'],
            ['value' => 'completed', 'label' => 'Terminé'],
            ['value' => 'failed', 'label' => 'Échec'],
            ['value' => 'cancelled', 'label' => 'Annulé'],
            ['value' => 'demande', 'label' => 'Demandée'],
            ['value' => 'en_cours', 'label' => 'En cours'],
            ['value' => 'accepte', 'label' => 'Acceptée'],
            ['value' => 'refuse', 'label' => 'Refusée'],
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

    private function importStatusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Terminé',
            'failed' => 'Échec',
            'processing' => 'Traitement',
            'cancelled' => 'Annulé',
            default => 'En attente',
        };
    }

    private function transferStatusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Terminé',
            'failed' => 'Échec',
            'running' => 'En cours',
            'cancelling' => 'Annulation',
            'cancelled' => 'Annulé',
            default => 'En attente',
        };
    }

    private function auditSeverity(AuditLog $log): string
    {
        return str_contains($log->action, 'failed') || str_contains($log->action, 'error')
            ? 'error'
            : 'info';
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

    private function compactPayload(mixed $payload): ?string
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        return $this->compactText(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 1200);
    }

    private function compactText(?string $value, int $limit): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit).'...'
            : $value;
    }

    private function firstLine(?string $value): string
    {
        $value = $this->compactText($value, 300) ?: 'Exception non renseignée.';

        return strtok($value, "\n") ?: $value;
    }
}
