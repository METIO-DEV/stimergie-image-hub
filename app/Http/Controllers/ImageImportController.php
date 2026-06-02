<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImageImportItem;
use App\Models\Import;
use App\Models\ImportItem;
use App\Models\Project;
use App\Support\ProjectImageStoragePath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ImageImportController extends Controller
{
    public function __construct(
        private readonly ProjectImageStoragePath $storagePath,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'total_items' => ['required', 'integer', 'min:1', 'max:500'],
            'total_bytes' => ['nullable', 'integer', 'min:0', 'max:2147483648'],
        ]);

        $project = Project::with('client')->findOrFail($data['project_id']);
        $this->authorizeProjectImport($request, $project);

        $import = Import::create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'started_by' => $request->user()->id,
            'source' => 'folder_upload',
            'status' => 'pending',
            'total_items' => $data['total_items'],
            'total_bytes' => $data['total_bytes'] ?? 0,
            'started_at' => now(),
            'metadata' => [
                'mode' => 'folder',
            ],
        ]);

        return response()->json($this->summary($import), 201);
    }

    public function show(Request $request, Import $import): JsonResponse
    {
        $import->loadMissing('project.client', 'items.image');
        $this->authorizeImport($request, $import);

        return response()->json($this->summary($import));
    }

    public function item(Request $request, Import $import): JsonResponse
    {
        $import->loadMissing('project.client');
        $this->authorizeImport($request, $import);
        abort_unless(in_array($import->status, ['pending', 'processing'], true), 409);

        $data = $request->validate([
            'file' => ['required', 'image', 'max:102400'],
            'relative_path' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $sourcePath = $file->getRealPath();
        abort_unless($sourcePath, 422);

        $checksum = hash_file('sha256', $sourcePath);
        $extension = $this->extension($file);
        $baseName = (string) Str::uuid();
        $objectKey = "{$this->storagePath->prefix($import->project)}/{$baseName}.{$extension}";
        $disk = (string) config('filesystems.image_disk', 'scaleway');

        $stream = fopen($sourcePath, 'r');
        abort_unless($stream !== false, 422);

        try {
            Storage::disk($disk)->put($objectKey, $stream, [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType() ?: 'application/octet-stream',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $item = $import->items()->create([
            'source_identifier' => $data['relative_path'] ?: $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'relative_path' => $data['relative_path'] ?? null,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => $checksum,
            'object_key_original' => $objectKey,
            'status' => 'uploaded',
            'metadata' => [
                'disk' => $disk,
            ],
        ]);

        if ($import->status === 'pending') {
            $import->forceFill(['status' => 'processing'])->save();
        }

        $import->refreshProgress();
        ProcessImageImportItem::dispatch($item->id);

        return response()->json($this->summary($import->fresh(['items.image', 'project.client'])), 201);
    }

    public function retryFailed(Request $request, Import $import): JsonResponse
    {
        $import->loadMissing('project.client');
        $this->authorizeImport($request, $import);

        $items = $import->items()
            ->where('status', 'failed')
            ->whereNotNull('object_key_original')
            ->get();

        foreach ($items as $item) {
            $item->forceFill([
                'status' => 'uploaded',
                'error_details' => null,
                'processed_at' => null,
            ])->save();

            ProcessImageImportItem::dispatch($item->id);
        }

        $import->refreshProgress();

        return response()->json($this->summary($import->fresh(['items.image', 'project.client'])));
    }

    private function authorizeImport(Request $request, Import $import): void
    {
        abort_unless($import->project instanceof Project, 404);
        $this->authorizeProjectImport($request, $import->project);
    }

    private function authorizeProjectImport(Request $request, Project $project): void
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin()
            || ($project->client && $user?->hasClientRole($project->client, ['owner', 'manager'])), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Import $import): array
    {
        $import->loadMissing('project.client', 'items.image');

        return [
            'id' => $import->id,
            'status' => $import->status,
            'projectId' => $import->project_id,
            'projectName' => $import->project?->name,
            'clientName' => $import->client?->name,
            'totalItems' => $import->total_items,
            'uploadedItems' => $import->uploaded_items,
            'processedItems' => $import->processed_items,
            'failedItems' => $import->failed_items,
            'duplicateItems' => $import->duplicate_items,
            'totalBytes' => $import->total_bytes,
            'startedAt' => $import->started_at?->toIso8601String(),
            'finishedAt' => $import->finished_at?->toIso8601String(),
            'items' => $import->items
                ->sortByDesc('id')
                ->take(25)
                ->values()
                ->map(fn (ImportItem $item) => [
                    'id' => $item->id,
                    'imageId' => $item->image_id,
                    'filename' => $item->original_filename ?: $item->source_identifier,
                    'relativePath' => $item->relative_path,
                    'status' => $item->status,
                    'sizeBytes' => $item->size_bytes,
                    'error' => $item->error_details,
                    'processedAt' => $item->processed_at?->toIso8601String(),
                ]),
        ];
    }

    private function extension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => $file->extension() ?: 'jpg',
        };
    }
}
