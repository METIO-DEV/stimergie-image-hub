<?php

namespace App\Http\Controllers;

use App\Jobs\RunAssetTransferJob;
use App\Jobs\RunBucketDatabaseSyncJob;
use App\Jobs\RunMissingWebVariantGenerationJob;
use App\Models\AssetFolderMapping;
use App\Models\AssetTransferJob;
use App\Models\Project;
use App\Support\O2SwitchAssetBrowser;
use App\Support\ProjectFolderMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AssetTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('AssetTransfers/Index', [
            'jobs' => $this->jobSummaries(),
        ]);
    }

    public function sources(Request $request, O2SwitchAssetBrowser $browser, ProjectFolderMatcher $folderMatcher): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        try {
            $ftpFolders = $browser->ftpFolders();
            $bucketFolders = $browser->bucketFolders();
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'folders' => [],
            ], 422);
        }

        return response()->json([
            'folders' => $this->sourceRows($ftpFolders, $bucketFolders, $folderMatcher),
            'folderMatches' => $this->folderMatchRows($ftpFolders, $bucketFolders, $folderMatcher),
            'webVariantAudits' => $this->webVariantAuditRows(),
            'projectOptions' => $this->projectOptions(),
            'refreshedAt' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request, O2SwitchAssetBrowser $browser): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        abort_if($this->activeJob(), 409, 'Un transfert est déjà en cours.');

        $data = $request->validate([
            'folders' => ['nullable', 'array', 'max:100'],
            'folders.*' => ['string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $folders = collect($data['folders'] ?? [])
            ->map(fn (string $folder) => trim($folder))
            ->filter()
            ->unique()
            ->values();

        if ($folders->isEmpty()) {
            $limit = (int) ($data['limit'] ?? 0);
            abort_if($limit < 1, 422, 'Sélectionnez des dossiers ou indiquez un nombre de dossiers à transférer.');

            $bucketFolders = $browser->bucketFolders();
            $folders = collect($browser->ftpFolders())
                ->reject(fn (string $folder) => array_key_exists($folder, $bucketFolders))
                ->take($limit)
                ->values();
        }

        abort_if($folders->isEmpty(), 422, 'Aucun dossier à transférer.');

        $job = AssetTransferJob::create([
            'started_by' => $request->user()->id,
            'status' => 'pending',
            'mode' => 'batch-copy',
            'total_folders' => $folders->count(),
            'folders' => $folders->all(),
            'completed_folders' => [],
            'failed_folder_details' => [],
            'metadata' => [
                'created_from' => 'temporary_asset_transfer_ui',
            ],
        ]);

        RunAssetTransferJob::dispatch($job->id);

        return response()->json([
            'job' => $this->jobSummary($job->fresh()),
        ], 201);
    }

    public function resyncBucket(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        abort_if($this->activeBucketDatabaseSync(), 409, 'Une resynchro bucket/base est déjà en cours.');

        $job = AssetTransferJob::create([
            'started_by' => $request->user()->id,
            'status' => 'pending',
            'mode' => 'bucket-db-sync',
            'total_folders' => 0,
            'folders' => [],
            'completed_folders' => [],
            'failed_folder_details' => [],
            'metadata' => [
                'created_from' => 'temporary_asset_transfer_ui',
            ],
        ]);

        RunBucketDatabaseSyncJob::dispatch($job->id)->onQueue('sync');

        return response()->json([
            'job' => $this->jobSummary($job->fresh()),
        ], 201);
    }

    public function generateWebVariants(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        abort_if($this->activeWebVariantGeneration(), 409, 'Une génération de variantes web est déjà en cours.');

        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $scopeLabel = 'Tous les projets';

        if (! empty($data['project_id'])) {
            $project = Project::query()
                ->with('client:id,name')
                ->findOrFail((int) $data['project_id']);
            $scopeLabel = trim(($project->client?->name ? "{$project->client->name} / " : '').$project->name);
        } elseif (! empty($data['folder'])) {
            $scopeLabel = trim((string) $data['folder']);
        }

        $job = AssetTransferJob::create([
            'started_by' => $request->user()->id,
            'status' => 'pending',
            'mode' => 'web-variant-generation',
            'total_folders' => 0,
            'folders' => [$scopeLabel],
            'completed_folders' => [],
            'failed_folder_details' => [],
            'metadata' => [
                'created_from' => 'temporary_asset_transfer_ui',
                'web_variant_generation' => [
                    'project_id' => $data['project_id'] ?? null,
                    'folder' => $data['folder'] ?? null,
                    'source_prefix' => 'photos',
                    'target_prefix' => 'images',
                    'scope_label' => $scopeLabel,
                ],
            ],
        ]);

        RunMissingWebVariantGenerationJob::dispatch($job->id)->onQueue('sync');

        return response()->json([
            'job' => $this->jobSummary($job->fresh()),
        ], 201);
    }

    public function mapFolder(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'folder' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        AssetFolderMapping::updateOrCreate(
            ['folder' => trim($data['folder'])],
            [
                'project_id' => (int) $data['project_id'],
                'status' => 'mapped',
                'created_by' => $request->user()->id,
                'metadata' => [
                    'source' => 'manual_mapping',
                    'mapped_at' => now()->toIso8601String(),
                ],
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function ignoreFolder(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'folder' => ['required', 'string', 'max:255'],
        ]);

        AssetFolderMapping::updateOrCreate(
            ['folder' => trim($data['folder'])],
            [
                'project_id' => null,
                'status' => 'ignored',
                'created_by' => $request->user()->id,
                'metadata' => [
                    'source' => 'manual_ignore',
                    'ignored_at' => now()->toIso8601String(),
                ],
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function autoMapFolders(
        Request $request,
        O2SwitchAssetBrowser $browser,
        ProjectFolderMatcher $folderMatcher,
    ): JsonResponse {
        $this->authorizeSuperAdmin($request);

        $ftpFolders = $browser->ftpFolders();
        $bucketFolders = $browser->bucketFolders();
        $mapped = 0;

        foreach ($this->folderMatchRows($ftpFolders, $bucketFolders, $folderMatcher) as $row) {
            if (
                $row['status'] !== 'suggested'
                || ($row['suggestion']['score'] ?? 0) < ProjectFolderMatcher::AUTO_MATCH_SCORE
            ) {
                continue;
            }

            AssetFolderMapping::updateOrCreate(
                ['folder' => $row['folder']],
                [
                    'project_id' => $row['suggestion']['id'],
                    'status' => 'mapped',
                    'created_by' => $request->user()->id,
                    'metadata' => [
                        'source' => 'auto_high_confidence',
                        'score' => $row['suggestion']['score'],
                        'matched_at' => now()->toIso8601String(),
                    ],
                ],
            );

            $mapped++;
        }

        return response()->json(['mapped' => $mapped]);
    }

    public function show(Request $request, AssetTransferJob $assetTransferJob): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        return response()->json([
            'job' => $this->jobSummary($assetTransferJob->fresh()),
        ]);
    }

    public function stop(Request $request, AssetTransferJob $assetTransferJob): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        if ($assetTransferJob->isActive()) {
            $assetTransferJob->forceFill([
                'status' => 'cancelling',
                'cancel_requested_at' => now(),
                'metadata' => [
                    ...($assetTransferJob->metadata ?? []),
                    'cancelled_by' => $request->user()->id,
                ],
            ])->save();

            if ($assetTransferJob->process_id) {
                $this->signalProcess((int) $assetTransferJob->process_id);
            }
        }

        return response()->json([
            'job' => $this->jobSummary($assetTransferJob->fresh()),
        ]);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    private function activeJob(): ?AssetTransferJob
    {
        return AssetTransferJob::query()
            ->whereIn('status', ['pending', 'running', 'cancelling'])
            ->where('mode', 'batch-copy')
            ->latest()
            ->first();
    }

    private function activeBucketDatabaseSync(): ?AssetTransferJob
    {
        return AssetTransferJob::query()
            ->whereIn('status', ['pending', 'running', 'cancelling'])
            ->where('mode', 'bucket-db-sync')
            ->latest()
            ->first();
    }

    private function activeWebVariantGeneration(): ?AssetTransferJob
    {
        return AssetTransferJob::query()
            ->whereIn('status', ['pending', 'running', 'cancelling'])
            ->where('mode', 'web-variant-generation')
            ->latest()
            ->first();
    }

    /**
     * @param  array<int, string>  $ftpFolders
     * @param  array<string, int>  $bucketFolders
     * @return array<int, array<string, mixed>>
     */
    private function sourceRows(array $ftpFolders, array $bucketFolders, ProjectFolderMatcher $folderMatcher): array
    {
        $projects = $this->projectsWithImageVariantCounts()
            ->get();
        $projectsById = $projects->keyBy('id');

        return collect([...$ftpFolders, ...array_keys($bucketFolders)])
            ->unique()
            ->reject(fn (string $folder) => $this->isSystemFolder($folder))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(function (string $folder) use ($bucketFolders, $folderMatcher, $ftpFolders, $projects, $projectsById): array {
                $mappedProject = $folderMatcher->mappedProject($folder);
                $project = ($mappedProject ? $projectsById->get($mappedProject->id) : null)
                    ?? $folderMatcher->exactProject($folder, $projects);
                $best = $project ? null : $folderMatcher->bestProject($folder, $projects);

                if (! $project && ($best['score'] ?? 0) >= ProjectFolderMatcher::AUTO_MATCH_SCORE) {
                    $project = $best['project'];
                }

                return [
                    'name' => $folder,
                    'onFtp' => in_array($folder, $ftpFolders, true),
                    'onBucket' => array_key_exists($folder, $bucketFolders),
                    'bucketFileCount' => $bucketFolders[$folder] ?? 0,
                    'projectId' => $project?->id,
                    'projectName' => $project?->name,
                    'databaseImageCount' => $project?->images_count ?? 0,
                    'imagesWithOriginalCount' => $project?->images_with_original_count ?? 0,
                    'webVariantReadyCount' => max(0, ($project?->images_with_original_count ?? 0) - ($project?->missing_web_variant_count ?? 0)),
                    'missingWebVariantCount' => $project?->missing_web_variant_count ?? 0,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $ftpFolders
     * @param  array<string, int>  $bucketFolders
     * @return array<int, array<string, mixed>>
     */
    private function folderMatchRows(array $ftpFolders, array $bucketFolders, ProjectFolderMatcher $folderMatcher): array
    {
        $projects = Project::query()
            ->with('client:id,name')
            ->withCount('images')
            ->orderBy('name')
            ->get();
        $mappings = AssetFolderMapping::query()
            ->with('project.client:id,name')
            ->get()
            ->keyBy('folder');

        return collect([...$ftpFolders, ...array_keys($bucketFolders)])
            ->unique()
            ->reject(fn (string $folder) => $this->isSystemFolder($folder))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(function (string $folder) use ($bucketFolders, $folderMatcher, $ftpFolders, $mappings, $projects): array {
                $mapping = $mappings->get($folder);
                $exact = $folderMatcher->exactProject($folder, $projects);
                $best = $folderMatcher->bestProject($folder, $projects);
                $suggestedProject = $best['project'];
                $status = 'unmatched';

                if ($mapping?->status === 'ignored') {
                    $status = 'ignored';
                } elseif ($mapping?->status === 'mapped' && $mapping->project) {
                    $status = 'mapped';
                } elseif ($exact) {
                    $status = 'exact';
                } elseif ($suggestedProject && $best['score'] >= ProjectFolderMatcher::SUGGESTION_SCORE) {
                    $status = 'suggested';
                }

                return [
                    'folder' => $folder,
                    'status' => $status,
                    'onFtp' => in_array($folder, $ftpFolders, true),
                    'onBucket' => array_key_exists($folder, $bucketFolders),
                    'bucketFileCount' => $bucketFolders[$folder] ?? 0,
                    'mappedProject' => $mapping?->project ? $this->projectOption($mapping->project) : null,
                    'exactProject' => $exact ? $this->projectOption($exact) : null,
                    'suggestion' => $suggestedProject ? [
                        ...$this->projectOption($suggestedProject),
                        'source' => $best['source'],
                        'score' => round($best['score'], 1),
                        'autoMappable' => $best['score'] >= ProjectFolderMatcher::AUTO_MATCH_SCORE,
                    ] : null,
                ];
            })
            ->all();
    }

    private function isSystemFolder(string $folder): bool
    {
        return in_array(Str::lower(trim($folder)), ['assets'], true)
            || str_contains($folder, ':');
    }

    /**
     * @return array<string, mixed>
     */
    private function projectOption(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'clientName' => $project->client?->name,
            'sourceFolder' => $project->source_folder,
            'imagesCount' => $project->images_count ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projectOptions(): array
    {
        return Project::query()
            ->with('client:id,name')
            ->withCount('images')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => $this->projectOption($project))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function webVariantAuditRows(): array
    {
        return $this->projectsWithImageVariantCounts()
            ->with('client:id,name')
            ->orderByDesc('missing_web_variant_count')
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project): bool => (int) $project->missing_web_variant_count > 0)
            ->map(fn (Project $project): array => [
                'projectId' => $project->id,
                'projectName' => $project->name,
                'clientName' => $project->client?->name,
                'sourceFolder' => $project->source_folder,
                'databaseImageCount' => (int) $project->images_count,
                'imagesWithOriginalCount' => (int) $project->images_with_original_count,
                'webVariantReadyCount' => max(0, (int) $project->images_with_original_count - (int) $project->missing_web_variant_count),
                'missingWebVariantCount' => (int) $project->missing_web_variant_count,
            ])
            ->all();
    }

    private function projectsWithImageVariantCounts()
    {
        return Project::query()
            ->withCount([
                'images',
                'images as images_with_original_count' => fn ($query) => $query->whereNotNull('object_key_original'),
                'images as missing_web_variant_count' => fn ($query) => $this->constrainMissingUsableWeb($query),
            ]);
    }

    private function constrainMissingUsableWeb($query): void
    {
        $query
            ->whereNotNull('object_key_original')
            ->where(function ($query) {
                $query
                    ->whereNull('object_key_web')
                    ->orWhereColumn('object_key_web', 'object_key_original')
                    ->orWhereColumn('object_key_web', 'object_key_hd');
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jobSummaries(): array
    {
        return AssetTransferJob::query()
            ->with('starter:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (AssetTransferJob $job) => $this->jobSummary($job))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSummary(AssetTransferJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status,
            'mode' => $job->mode,
            'startedBy' => $job->starter?->name,
            'totalFolders' => $job->total_folders,
            'processedFolders' => $job->processed_folders,
            'failedFolders' => $job->failed_folders,
            'currentFolder' => $job->current_folder,
            'folders' => $job->folders ?? [],
            'completedFolders' => $job->completed_folders ?? [],
            'failedFolderDetails' => $job->failed_folder_details ?? [],
            'cancelRequestedAt' => $job->cancel_requested_at?->toIso8601String(),
            'startedAt' => $job->started_at?->toIso8601String(),
            'finishedAt' => $job->finished_at?->toIso8601String(),
            'createdAt' => $job->created_at?->toIso8601String(),
            'syncTotals' => $this->syncTotals($job),
            'webVariantTotals' => $this->webVariantTotals($job),
            'log' => $this->logTail($job),
        ];
    }

    /**
     * @return array{bucketImages: int, created: int, updated: int, skipped: int}|null
     */
    private function syncTotals(AssetTransferJob $job): ?array
    {
        if ($job->mode !== 'bucket-db-sync') {
            return null;
        }

        $totals = ($job->metadata ?? [])['bucket_db_sync']['totals'] ?? [];

        return [
            'bucketImages' => (int) ($totals['bucket_images'] ?? 0),
            'created' => (int) ($totals['created'] ?? 0),
            'updated' => (int) ($totals['updated'] ?? 0),
            'skipped' => (int) ($totals['skipped'] ?? 0),
        ];
    }

    /**
     * @return array{checked: int, generated: int, missingOriginals: int, failed: int}|null
     */
    private function webVariantTotals(AssetTransferJob $job): ?array
    {
        if ($job->mode !== 'web-variant-generation') {
            return null;
        }

        $totals = ($job->metadata ?? [])['web_variant_generation']['totals'] ?? [];

        return [
            'checked' => (int) ($totals['checked'] ?? 0),
            'generated' => (int) ($totals['generated'] ?? 0),
            'missingOriginals' => (int) ($totals['missing_originals'] ?? 0),
            'failed' => (int) ($totals['failed'] ?? 0),
        ];
    }

    private function logTail(AssetTransferJob $job): string
    {
        if (! $job->log_file || ! File::exists($job->log_file)) {
            return '';
        }

        $content = File::get($job->log_file);

        return strlen($content) > 60000 ? substr($content, -60000) : $content;
    }

    private function signalProcess(int $pid): void
    {
        if ($pid < 1) {
            return;
        }

        if (function_exists('posix_kill')) {
            @posix_kill($pid, 15);
        }
    }
}
