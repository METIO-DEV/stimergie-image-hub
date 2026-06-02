<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAssignImagesProjectRequest;
use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageRequest;
use App\Models\Image;
use App\Models\Project;
use App\Models\Tag;
use App\Support\ImageUrlResolver;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function __construct(
        private readonly ImageVariantGenerator $imageVariants,
        private readonly ImageUrlResolver $imageUrls,
        private readonly ProjectAccess $projectAccess,
    ) {}

    public function bulkProject(BulkAssignImagesProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $project = Project::findOrFail($data['project_id']);

        Image::query()
            ->whereIn('id', $data['image_ids'])
            ->update([
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Images liees au projet.');
    }

    public function store(StoreImageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $project = Project::findOrFail($data['project_id']);

        DB::transaction(function () use ($data, $project, $request): void {
            $fileData = $this->imageVariants->store($request->file('file'));

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
                'storage_provider' => $fileData['disk'],
                'object_key_original' => $fileData['original'],
                'object_key_web' => $fileData['web'],
                'object_key_thumb' => $fileData['thumb'],
                'object_key_hd' => $fileData['hd'],
                'legacy_url' => $fileData['url'],
                'legacy_thumbnail_url' => $fileData['url'],
                'status' => $data['status'],
                'processed_at' => now(),
            ]);

            $this->imageVariants->syncImageVariants($image, $fileData['variants']);
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
                $fileData = $this->imageVariants->store($request->file('file'));
                $payload = [
                    ...$payload,
                    'orientation' => $data['orientation'] ?: $fileData['orientation'],
                    'width' => $fileData['width'],
                    'height' => $fileData['height'],
                    'mime_type' => $fileData['mime_type'],
                    'size_bytes' => $fileData['size_bytes'],
                    'checksum' => $fileData['checksum'],
                    'storage_provider' => $fileData['disk'],
                    'object_key_original' => $fileData['original'],
                    'object_key_web' => $fileData['web'],
                    'object_key_thumb' => $fileData['thumb'],
                    'object_key_hd' => $fileData['hd'],
                    'legacy_url' => $fileData['url'],
                    'legacy_thumbnail_url' => $fileData['url'],
                    'processed_at' => now(),
                    'processing_error' => null,
                ];
            }

            $image->update($payload);
            if (isset($fileData)) {
                $this->imageVariants->syncImageVariants($image, $fileData['variants']);
            }
            $this->syncTags($image, $data['tags'] ?? '');
        });

        return back()->with('success', 'Image mise a jour.');
    }

    public function download(Request $request, Image $image)
    {
        abort_unless($this->projectAccess->userCanViewImage($request->user(), $image), 403);

        $variant = (string) $request->query('variant', 'hd');
        abort_unless(in_array($variant, ['web', 'hd'], true), 404);

        $source = $this->imageUrls->downloadSource($image, $variant);
        abort_unless($source['objectKey'], 404);
        abort_if(str_contains($source['objectKey'], 'legacy/'), 404);
        abort_unless(Storage::disk($source['disk'])->exists($source['objectKey']), 404);

        return Storage::disk($source['disk'])->download(
            $source['objectKey'],
            $this->downloadFilename($image, $source['objectKey'], $variant),
        );
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

    private function downloadFilename(Image $image, string $objectKey, string $variant): string
    {
        $extension = pathinfo($objectKey, PATHINFO_EXTENSION) ?: 'jpg';
        $name = Str::slug($image->title) ?: "image-{$image->id}";

        return "{$name}-{$variant}.{$extension}";
    }
}
