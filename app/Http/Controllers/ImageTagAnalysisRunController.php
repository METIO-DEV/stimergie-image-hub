<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeImageTags;
use App\Models\Image;
use App\Models\ImageTagAnalysisRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageTagAnalysisRunController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManageImages($request);

        return response()->json($this->dashboard($request));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManageImages($request);

        $data = $request->validate([
            'mode' => ['nullable', 'string', 'in:missing,all'],
            'image_id' => ['nullable', 'integer', 'exists:images,id'],
        ]);

        abort_if($this->activeRun(), 409, 'Une analyse de tags est déjà en cours.');

        $imageIds = $this->imageIdsForRun($request, $data);
        abort_if($imageIds === [], 422, 'Aucune image prête à analyser.');

        $run = ImageTagAnalysisRun::create([
            'started_by' => $request->user()->id,
            'status' => 'pending',
            'mode' => isset($data['image_id']) ? 'single' : ($data['mode'] ?? 'missing'),
            'total_images' => count($imageIds),
            'started_at' => now(),
            'image_ids' => $imageIds,
        ]);

        AnalyzeImageTags::dispatch($run->id);

        return response()->json($this->dashboard($request), 201);
    }

    public function stop(Request $request, ImageTagAnalysisRun $run): JsonResponse
    {
        $this->authorizeManageImages($request);

        if ($run->isActive()) {
            $metadata = $run->metadata ?? [];

            $run->forceFill([
                'status' => 'cancelled',
                'finished_at' => now(),
                'metadata' => [
                    ...$metadata,
                    'cancelled_by' => $request->user()->id,
                    'cancelled_at' => now()->toIso8601String(),
                ],
            ])->save();
        }

        return response()->json($this->dashboard($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(Request $request): array
    {
        $baseQuery = $this->manageableImagesQuery($request);
        $total = (clone $baseQuery)->count();
        $withTags = (clone $baseQuery)->has('tags')->count();
        $withoutTags = max(0, $total - $withTags);

        $latestRun = ImageTagAnalysisRun::query()
            ->with('currentImage:id,title')
            ->latest()
            ->first();

        return [
            'stats' => [
                'total' => $total,
                'withTags' => $withTags,
                'withoutTags' => $withoutTags,
                'aiTagged' => (clone $baseQuery)
                    ->where('metadata->tag_source', 'ai')
                    ->count(),
            ],
            'run' => $latestRun ? $this->runSummary($latestRun) : null,
        ];
    }

    /**
     * @param  array{mode?: string, image_id?: int}  $data
     * @return array<int, int>
     */
    private function imageIdsForRun(Request $request, array $data): array
    {
        $query = $this->manageableImagesQuery($request)
            ->where('status', 'ready')
            ->orderBy('id');

        if (isset($data['image_id'])) {
            $query->whereKey((int) $data['image_id']);
        } elseif (($data['mode'] ?? 'missing') === 'missing') {
            $query->doesntHave('tags');
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function activeRun(): ?ImageTagAnalysisRun
    {
        return ImageTagAnalysisRun::query()
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();
    }

    /**
     * @return Builder<Image>
     */
    private function manageableImagesQuery(Request $request): Builder
    {
        $user = $request->user();

        return Image::query()
            ->where('storage_provider', 'scaleway')
            ->where('object_key_original', 'like', 'photos/%')
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $clientIds = $user->clientMemberships()
                    ->where('status', 'active')
                    ->whereIn('role', ['owner', 'manager'])
                    ->pluck('client_id');

                $query->whereIn('client_id', $clientIds);
            });
    }

    private function authorizeManageImages(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin() || $user?->hasAnyClientRole(['owner', 'manager']), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function runSummary(ImageTagAnalysisRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'mode' => $run->mode,
            'totalImages' => $run->total_images,
            'processedImages' => $run->processed_images,
            'failedImages' => $run->failed_images,
            'currentImage' => $run->currentImage
                ? [
                    'id' => $run->currentImage->id,
                    'title' => $run->currentImage->title,
                ]
                : null,
            'startedAt' => $run->started_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
        ];
    }
}
