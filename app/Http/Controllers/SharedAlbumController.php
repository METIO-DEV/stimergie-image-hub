<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSharedAlbumRequest;
use App\Models\Image;
use App\Models\SharedAlbum;
use App\Support\ImageUrlResolver;
use App\Support\ProjectAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SharedAlbumController extends Controller
{
    public function __construct(
        private readonly ImageUrlResolver $imageUrls,
        private readonly ProjectAccess $projectAccess,
    ) {}

    public function store(StoreSharedAlbumRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $images = Image::query()
            ->with(['client:id,name', 'project.accessPeriods', 'tags:id,name'])
            ->whereIn('id', $data['image_ids'])
            ->get();

        abort_unless($images->count() === count(array_unique($data['image_ids'])), 422);
        abort_unless($images->every(fn (Image $image) => $this->projectAccess->userCanViewImage($request->user(), $image)), 403);

        $album = SharedAlbum::create([
            'client_id' => $this->singleClientId($images),
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'share_key' => $this->shareKey(),
            'starts_at' => $data['starts_at'] ?: null,
            'expires_at' => $data['expires_at'] ?: now()->addMonth(),
            'is_active' => true,
            'metadata' => [
                'recipients' => $this->recipients($data['recipients'] ?? ''),
                'message' => $data['message'] ?? null,
            ],
        ]);

        $album->images()->sync(
            $images->values()->mapWithKeys(fn (Image $image, int $index) => [
                $image->id => ['position' => $index + 1],
            ])->all(),
        );

        return back()->with('success', 'Album partagé créé : '.route('shared-albums.show', $album->share_key));
    }

    public function show(string $shareKey): Response
    {
        $album = $this->activeAlbum($shareKey);

        return Inertia::render('SharedAlbums/Show', [
            'album' => [
                'name' => $album->name,
                'description' => $album->description,
                'message' => $album->metadata['message'] ?? null,
                'expiresAt' => $album->expires_at?->toDateString(),
                'downloadUrl' => route('shared-albums.download', $album->share_key),
                'images' => $album->images->map(fn (Image $image) => [
                    'id' => $image->id,
                    'title' => $image->title,
                    'description' => $image->description,
                    'clientName' => $image->client?->name,
                    'projectName' => $image->project?->name,
                    'thumbUrl' => $this->imageUrls->thumbnailUrl($image),
                    'imageUrl' => $this->imageUrls->displayUrl($image),
                    'tags' => $image->tags->pluck('name')->values(),
                ]),
            ],
        ]);
    }

    public function download(string $shareKey): BinaryFileResponse
    {
        $album = $this->activeAlbum($shareKey);
        $zipPath = storage_path('app/shared-albums/'.$album->share_key.'.zip');

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        $album->images->each(function (Image $image, int $index) use ($zip): void {
            $source = $this->imageUrls->downloadSource($image, 'web');
            $objectKey = $source['objectKey'];

            if (! $objectKey || str_contains($objectKey, 'legacy/')) {
                return;
            }

            $disk = Storage::disk($source['disk']);

            if (! $disk->exists($objectKey)) {
                return;
            }

            $extension = pathinfo($objectKey, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)
                .'-'.(Str::slug($image->title) ?: "image-{$image->id}")
                .'.'.$extension;

            $zip->addFromString($filename, $disk->get($objectKey));
        });

        $zip->close();

        return response()
            ->download($zipPath, (Str::slug($album->name) ?: 'album-partage').'.zip')
            ->deleteFileAfterSend();
    }

    private function activeAlbum(string $shareKey): SharedAlbum
    {
        $album = SharedAlbum::query()
            ->with(['images' => fn ($query) => $query
                ->with(['client:id,name', 'project:id,name', 'tags:id,name'])
                ->orderBy('shared_album_images.position')])
            ->where('share_key', $shareKey)
            ->where('is_active', true)
            ->firstOrFail();

        abort_if($album->starts_at && $album->starts_at->isFuture(), 404);
        abort_if($album->expires_at && $album->expires_at->isPast(), 404);

        return $album;
    }

    private function shareKey(): string
    {
        do {
            $key = Str::lower(Str::random(32));
        } while (SharedAlbum::query()->where('share_key', $key)->exists());

        return $key;
    }

    private function recipients(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', $value) ?: [])
            ->map(fn (string $recipient) => trim($recipient))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function singleClientId($images): ?int
    {
        $clientIds = $images->pluck('client_id')->unique()->values();

        return $clientIds->count() === 1 ? $clientIds->first() : null;
    }
}
