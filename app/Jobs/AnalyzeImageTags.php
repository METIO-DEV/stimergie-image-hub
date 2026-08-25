<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\ImageTagAnalysisRun;
use App\Support\ImageTagSyncer;
use App\Support\OpenAiImageTagAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyzeImageTags implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public readonly int $runId,
        public readonly int $position = 0,
    ) {}

    public function handle(OpenAiImageTagAnalyzer $analyzer, ImageTagSyncer $tagSyncer): void
    {
        $run = ImageTagAnalysisRun::find($this->runId);

        if (! $run || ! $run->isActive()) {
            return;
        }

        $imageIds = $run->image_ids ?? [];
        $imageId = $imageIds[$this->position] ?? null;

        if (! $imageId) {
            $this->finishRun($run);

            return;
        }

        $image = Image::query()->with('client')->find($imageId);

        if (! $image || $image->status !== 'ready') {
            $this->markCurrentImageSkipped($run, (int) $imageId, 'Image introuvable ou non prête.');
            $this->dispatchNext($run);

            return;
        }

        $this->markCurrentImageProcessing($run, $image);

        try {
            $tags = $analyzer->analyzeStoredImage($image);

            DB::transaction(function () use ($image, $run, $tagSyncer, $tags): void {
                $tagSyncer->syncArray($image, $tags);

                $image->update([
                    'metadata' => [
                        ...($image->metadata ?? []),
                        'tag_source' => 'ai',
                        'ai_tags_applied_at' => now()->toIso8601String(),
                        'ai_tag_analysis_status' => 'completed',
                        'ai_tag_analysis_run_id' => $run->id,
                        'ai_tag_analysis_error' => null,
                    ],
                ]);

                $run->increment('processed_images');
            });
        } catch (Throwable $exception) {
            $this->markCurrentImageFailed($run, $image, $exception);
        }

        $this->dispatchNext($run->fresh());
    }

    private function markCurrentImageProcessing(ImageTagAnalysisRun $run, Image $image): void
    {
        $run->forceFill([
            'status' => 'processing',
            'current_image_id' => $image->id,
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $image->update([
            'metadata' => [
                ...($image->metadata ?? []),
                'ai_tag_analysis_status' => 'processing',
                'ai_tag_analysis_run_id' => $run->id,
                'ai_tag_analysis_error' => null,
            ],
        ]);
    }

    private function markCurrentImageSkipped(ImageTagAnalysisRun $run, int $imageId, string $reason): void
    {
        $image = Image::find($imageId);

        if ($image) {
            $image->update([
                'metadata' => [
                    ...($image->metadata ?? []),
                    'ai_tag_analysis_status' => 'skipped',
                    'ai_tag_analysis_run_id' => $run->id,
                    'ai_tag_analysis_error' => $reason,
                ],
            ]);
        }

        $run->increment('failed_images');
    }

    private function markCurrentImageFailed(ImageTagAnalysisRun $run, Image $image, Throwable $exception): void
    {
        $image->update([
            'metadata' => [
                ...($image->metadata ?? []),
                'ai_tag_analysis_status' => 'failed',
                'ai_tag_analysis_run_id' => $run->id,
                'ai_tag_analysis_error' => $exception->getMessage(),
            ],
        ]);

        $run->increment('failed_images');
    }

    private function dispatchNext(?ImageTagAnalysisRun $run): void
    {
        if (! $run || ! $run->isActive()) {
            return;
        }

        $nextPosition = $this->position + 1;

        if ($nextPosition >= $run->total_images) {
            $this->finishRun($run);

            return;
        }

        self::dispatch($run->id, $nextPosition);
    }

    private function finishRun(ImageTagAnalysisRun $run): void
    {
        $status = $run->failed_images > 0 ? 'failed' : 'completed';

        $run->forceFill([
            'status' => $status,
            'current_image_id' => null,
            'finished_at' => now(),
        ])->save();
    }
}
