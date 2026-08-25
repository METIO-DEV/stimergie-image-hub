<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAssignImagesProjectRequest;
use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageRequest;
use App\Models\AuditLog;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\Project;
use App\Support\ImageRightsExtensionRequestMailer;
use App\Support\ImageTagSyncer;
use App\Support\ImageUrlResolver;
use App\Support\ImageVariantGenerator;
use App\Support\ObjectStoragePolicy;
use App\Support\ProjectAccess;
use App\Support\ProjectImageStoragePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImageController extends Controller
{
    public function __construct(
        private readonly ImageVariantGenerator $imageVariants,
        private readonly ImageUrlResolver $imageUrls,
        private readonly ProjectImageStoragePath $storagePath,
        private readonly ProjectAccess $projectAccess,
        private readonly ImageTagSyncer $tagSyncer,
        private readonly ObjectStoragePolicy $storagePolicy,
        private readonly ImageRightsExtensionRequestMailer $rightsExtensionMailer,
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

        return back()->with('success', 'Images liées au projet.');
    }

    public function store(StoreImageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $project = Project::findOrFail($data['project_id']);

        DB::transaction(function () use ($data, $project, $request): void {
            $fileData = $this->imageVariants->store(
                $request->file('file'),
                $this->storagePath->prefix($project),
            );

            $image = Image::create([
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'orientation' => $data['orientation'] ?: $fileData['orientation'],
                'rights_starts_at' => $data['rights_starts_at'] ?? null,
                'rights_ends_at' => $data['rights_ends_at'] ?? null,
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
                'legacy_url' => $fileData['url'],
                'legacy_thumbnail_url' => $fileData['url'],
                'status' => $data['status'],
                'processed_at' => now(),
                'metadata' => [
                    'tag_source' => $data['tag_source'] ?? 'manual',
                    'ai_tags_applied_at' => ($data['tag_source'] ?? null) === 'ai' ? now()->toIso8601String() : null,
                ],
            ]);

            $this->imageVariants->syncImageVariants($image, $fileData['variants']);
            $this->tagSyncer->syncString($image, $data['tags'] ?? '');
        });

        return back()->with('success', 'Image ajoutée.');
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
                'rights_starts_at' => $data['rights_starts_at'] ?? null,
                'rights_ends_at' => $data['rights_ends_at'] ?? null,
                'metadata' => [
                    ...($image->metadata ?? []),
                    'tag_source' => $data['tag_source'] ?? 'manual',
                    'ai_tags_applied_at' => ($data['tag_source'] ?? null) === 'ai'
                        ? now()->toIso8601String()
                        : data_get($image->metadata, 'ai_tags_applied_at'),
                ],
            ];

            if ($request->hasFile('file')) {
                $fileData = $this->imageVariants->store(
                    $request->file('file'),
                    $this->storagePath->prefix($project),
                );
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
                    'object_key_thumb' => $fileData['thumb'] ?? null,
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
            $this->tagSyncer->syncString($image, $data['tags'] ?? '');
        });

        return back()->with('success', 'Image mise à jour.');
    }

    public function download(Request $request, Image $image)
    {
        abort_unless($this->projectAccess->userCanViewImage($request->user(), $image), 403);
        abort_if($image->rightsAreExpired(), 403);

        $variant = (string) $request->query('variant', 'hd');
        abort_unless(in_array($variant, ['web', 'hd'], true), 404);

        $source = $this->imageUrls->downloadSource($image, $variant);
        abort_unless($source['objectKey'], 404);
        abort_if(str_contains($source['objectKey'], 'legacy/'), 404);
        abort_unless(Storage::disk($source['disk'])->exists($source['objectKey']), 404);

        $filename = $this->downloadFilename($image, $source['objectKey'], $variant);

        try {
            return redirect()->away(Storage::disk($source['disk'])->temporaryUrl(
                $source['objectKey'],
                now()->addMinutes(10),
                $this->storagePolicy->temporaryResponseOptions(
                    contentDisposition: 'attachment; filename="'.$filename.'"',
                ),
            ));
        } catch (Throwable) {
            return Storage::disk($source['disk'])->download($source['objectKey'], $filename);
        }
    }

    private function downloadFilename(Image $image, string $objectKey, string $variant): string
    {
        $extension = pathinfo($objectKey, PATHINFO_EXTENSION) ?: 'jpg';
        $name = Str::slug($image->title) ?: "image-{$image->id}";

        return "{$name}-{$variant}.{$extension}";
    }

    public function requestRightsExtension(Request $request, Image $image): RedirectResponse
    {
        abort_unless($this->projectAccess->userCanViewImage($request->user(), $image), 403);

        $alreadyRequested = false;
        $notRequestable = false;

        $rightsRequest = DB::transaction(function () use ($image, $request, &$alreadyRequested, &$notRequestable): ?ImageRightsExtensionRequest {
            $image = Image::query()
                ->whereKey($image->id)
                ->lockForUpdate()
                ->firstOrFail();

            $image->load(['client', 'project', 'latestRightsExtensionRequest']);

            if (! $image->canRequestRightsExtension()) {
                $alreadyRequested = $image->rights_extension_requested_at !== null
                    || ($image->latestRightsExtensionRequest instanceof ImageRightsExtensionRequest
                        && ! $image->latestRightsExtensionRequest->isClosed());
                $notRequestable = ! $alreadyRequested;

                return null;
            }

            $rightsRequest = ImageRightsExtensionRequest::create([
                'image_id' => $image->id,
                'client_id' => $image->client_id,
                'project_id' => $image->project_id,
                'requested_by' => $request->user()->id,
                'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
                'rights_ends_at' => $image->rights_ends_at,
                'metadata' => [
                    'image_title' => $image->title,
                    'client_name' => $image->client?->name,
                    'project_name' => $image->project?->name,
                ],
            ]);

            $image->forceFill([
                'rights_extension_requested_at' => now(),
                'rights_extension_requested_by' => $request->user()->id,
            ])->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'image.rights_extension_requested',
                'subject_type' => Image::class,
                'subject_id' => $image->id,
                'properties' => [
                    'rights_extension_request_id' => $rightsRequest->id,
                    'image_title' => $image->title,
                    'client_id' => $image->client_id,
                    'project_id' => $image->project_id,
                    'status' => $rightsRequest->status,
                    'rights_ends_at' => $image->rights_ends_at?->toDateString(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $rightsRequest;
        });

        if ($alreadyRequested) {
            return back()->with('success', 'Demande d’extension de cession déjà enregistrée.');
        }

        if ($notRequestable || ! $rightsRequest) {
            return back()->with('warning', "Cette image ne peut pas faire l'objet d'une demande d'extension de cession.");
        }

        try {
            $mailSent = $this->rightsExtensionMailer->send($rightsRequest);
        } catch (Throwable) {
            $mailSent = false;
        }

        if (! $mailSent) {
            return back()
                ->with('success', 'Demande d’extension de cession enregistrée.')
                ->with('warning', "L'email de notification n'a pas pu être envoyé. Vérifiez la configuration email.");
        }

        return back()->with('success', 'Demande d’extension de cession envoyée.');
    }
}
