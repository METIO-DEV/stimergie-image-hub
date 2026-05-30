<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageRequest;
use App\Models\Image;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function store(StoreImageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $project = Project::findOrFail($data['project_id']);

        DB::transaction(function () use ($data, $project, $request): void {
            $fileData = $this->storeFile($request->file('file'));

            $image = Image::create([
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'orientation' => $data['orientation'] ?: $fileData['orientation'],
                'width' => $fileData['width'],
                'height' => $fileData['height'],
                'mime_type' => $fileData['mime_type'],
                'size_bytes' => $fileData['size_bytes'],
                'checksum' => $fileData['checksum'],
                'storage_provider' => 'public',
                'object_key_original' => $fileData['path'],
                'object_key_web' => $fileData['path'],
                'object_key_thumb' => $fileData['path'],
                'object_key_hd' => $fileData['path'],
                'legacy_url' => $fileData['url'],
                'legacy_thumbnail_url' => $fileData['url'],
                'status' => $data['status'],
                'processed_at' => now(),
            ]);

            $this->syncTags($image, $data['tags'] ?? '');
        });

        return back()->with('success', 'Image ajoutee.');
    }

    public function update(UpdateImageRequest $request, Image $image): RedirectResponse
    {
        $data = $request->validated();
        $project = Project::findOrFail($data['project_id']);

        DB::transaction(function () use ($data, $image, $project, $request): void {
            $payload = [
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'orientation' => $data['orientation'] ?: $image->orientation,
                'status' => $data['status'],
            ];

            if ($request->hasFile('file')) {
                $fileData = $this->storeFile($request->file('file'));
                $payload = [
                    ...$payload,
                    'orientation' => $data['orientation'] ?: $fileData['orientation'],
                    'width' => $fileData['width'],
                    'height' => $fileData['height'],
                    'mime_type' => $fileData['mime_type'],
                    'size_bytes' => $fileData['size_bytes'],
                    'checksum' => $fileData['checksum'],
                    'storage_provider' => 'public',
                    'object_key_original' => $fileData['path'],
                    'object_key_web' => $fileData['path'],
                    'object_key_thumb' => $fileData['path'],
                    'object_key_hd' => $fileData['path'],
                    'legacy_url' => $fileData['url'],
                    'legacy_thumbnail_url' => $fileData['url'],
                    'processed_at' => now(),
                    'processing_error' => null,
                ];
            }

            $image->update($payload);
            $this->syncTags($image, $data['tags'] ?? '');
        });

        return back()->with('success', 'Image mise a jour.');
    }

    /**
     * @return array{path: string, url: string, width: int|null, height: int|null, orientation: string|null, mime_type: string|null, size_bytes: int|null, checksum: string}
     */
    private function storeFile(UploadedFile $file): array
    {
        $path = $file->store('images/originals', 'public');
        $size = @getimagesize($file->getRealPath());
        $width = $size ? $size[0] : null;
        $height = $size ? $size[1] : null;

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'width' => $width,
            'height' => $height,
            'orientation' => $this->orientation($width, $height),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    private function orientation(?int $width, ?int $height): ?string
    {
        if (! $width || ! $height) {
            return null;
        }

        if ($width === $height) {
            return 'square';
        }

        return $width > $height ? 'landscape' : 'portrait';
    }

    private function syncTags(Image $image, ?string $tags): void
    {
        $tagIds = collect(explode(',', $tags ?? ''))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique(fn (string $tag) => Str::lower($tag))
            ->map(function (string $tag) {
                return Tag::query()->firstOrCreate(
                    ['slug' => Str::slug($tag)],
                    ['name' => $tag],
                )->id;
            });

        $image->tags()->sync($tagIds);
    }
}
