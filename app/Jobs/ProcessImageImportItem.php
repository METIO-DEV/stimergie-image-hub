<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\ImportItem;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectImageStoragePath;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessImageImportItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $importItemId,
    ) {}

    public function handle(ImageVariantGenerator $imageVariants, ProjectImageStoragePath $storagePath): void
    {
        $item = ImportItem::with('import.project.client')->find($this->importItemId);

        if (! $item || ! in_array($item->status, ['uploaded', 'failed'], true)) {
            return;
        }

        $item->forceFill([
            'status' => 'processing',
            'attempts' => $item->attempts + 1,
            'error_details' => null,
        ])->save();

        try {
            $import = $item->import;
            $project = $import->project;

            $duplicate = Image::query()
                ->where('project_id', $project->id)
                ->where('checksum', $item->checksum)
                ->whereNotNull('checksum')
                ->where('status', 'ready')
                ->first();

            if ($duplicate instanceof Image) {
                $item->forceFill([
                    'image_id' => $duplicate->id,
                    'status' => 'duplicate',
                    'processed_at' => now(),
                ])->save();

                $import->refreshProgress();

                return;
            }

            $image = $item->image_id ? Image::find($item->image_id) : null;

            if (! $image) {
                $image = DB::transaction(function () use ($import, $item, $project): Image {
                    $image = Image::create([
                        'client_id' => $project->client_id,
                        'project_id' => $project->id,
                        'created_by' => $import->started_by,
                        'title' => $this->title($item),
                        'description' => null,
                        'mime_type' => $item->mime_type,
                        'size_bytes' => $item->size_bytes,
                        'checksum' => $item->checksum,
                        'storage_provider' => config('filesystems.image_disk', 'scaleway'),
                        'object_key_original' => $item->object_key_original,
                        'status' => 'processing',
                        'metadata' => [
                            'import_id' => $import->id,
                            'import_item_id' => $item->id,
                            'relative_path' => $item->relative_path,
                        ],
                    ]);

                    $item->forceFill(['image_id' => $image->id])->save();

                    return $image;
                });
            } else {
                $image->update([
                    'status' => 'processing',
                    'processing_error' => null,
                ]);
            }

            $fileData = $imageVariants->generateFromOriginal(
                $image,
                $storagePath->prefix($project),
            );

            DB::transaction(function () use ($fileData, $image, $imageVariants, $item): void {
                $image->update([
                    'orientation' => $fileData['orientation'],
                    'width' => $fileData['width'],
                    'height' => $fileData['height'],
                    'mime_type' => $fileData['mime_type'],
                    'size_bytes' => $fileData['size_bytes'],
                    'checksum' => $fileData['checksum'],
                    'storage_provider' => $fileData['disk'],
                    'object_key_original' => $fileData['original'],
                    'object_key_web' => $fileData['web'],
                    'object_key_thumb' => $fileData['thumb'] ?? null,
                    'object_key_hd' => $fileData['hd'],
                    'legacy_url' => null,
                    'legacy_thumbnail_url' => null,
                    'status' => 'ready',
                    'processed_at' => now(),
                    'processing_error' => null,
                ]);

                $imageVariants->syncImageVariants($image, $fileData['variants']);

                $item->forceFill([
                    'image_id' => $image->id,
                    'status' => 'done',
                    'processed_at' => now(),
                ])->save();
            });

            $item->import->refreshProgress();
        } catch (Throwable $exception) {
            if ($item->image_id) {
                Image::query()
                    ->whereKey($item->image_id)
                    ->update([
                        'status' => 'failed',
                        'processing_error' => $exception->getMessage(),
                    ]);
            }

            $item->forceFill([
                'status' => 'failed',
                'error_details' => $exception->getMessage(),
                'processed_at' => now(),
            ])->save();

            $item->import->refreshProgress();

            throw $exception;
        }
    }

    private function title(ImportItem $item): string
    {
        $filename = $item->original_filename ?: basename($item->source_identifier);
        $title = pathinfo($filename, PATHINFO_FILENAME);

        return Str::of($title)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString() ?: "Image importée {$item->id}";
    }
}
