<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDownloadRequest;
use App\Jobs\PrepareDownloadArchive;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Support\ImageExportPresets;
use App\Support\ImageUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DownloadController extends Controller
{
    public function __construct(private readonly ImageUrlResolver $imageUrls) {}

    public function store(StoreDownloadRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $variant = $data['variant'];
        $images = Image::query()
            ->with(['client:id,name', 'project:id,name'])
            ->whereIn('id', $data['image_ids'])
            ->get();

        $job = DownloadJob::create([
            'user_id' => $request->user()->id,
            'client_id' => $this->singleClientId($images),
            'title' => $this->title($images->count(), $variant, $data['crop_preset'] ?? null),
            'status' => 'pending',
            'is_hd' => $variant === 'hd',
            'image_count' => $images->count(),
            'storage_provider' => config('filesystems.image_disk', 'scaleway'),
            'payload' => [
                'variant' => $variant,
                'requested_image_ids' => $images->pluck('id')->values(),
                ...($variant === 'crop' ? [
                    'crop_preset' => $data['crop_preset'],
                    'crop_source' => $data['crop_source'],
                    'crops' => $data['crops'],
                ] : []),
            ],
        ]);

        PrepareDownloadArchive::dispatch($job->id);

        return redirect()
            ->route('downloads.index')
            ->with('success', 'Demande de téléchargement ajoutée à la file.');
    }

    public function show(Request $request, DownloadJob $downloadJob): RedirectResponse
    {
        $this->authorizeDownload($request, $downloadJob);

        abort_unless($downloadJob->status === 'ready', 404);
        abort_if(
            $downloadJob->download_url_expires_at && $downloadJob->download_url_expires_at->isPast(),
            404,
        );

        if ($downloadJob->object_key) {
            return redirect()->away($this->downloadUrl($downloadJob));
        }

        abort_unless($downloadJob->download_url, 404);
        abort_if(str_contains($downloadJob->download_url, 'stimergie.fr'), 404);

        return redirect()->away($downloadJob->download_url);
    }

    private function authorizeDownload(Request $request, DownloadJob $downloadJob): void
    {
        $user = $request->user();

        abort_unless($user, 403);

        if ($user->isSuperAdmin() || $downloadJob->user_id === $user->id) {
            return;
        }

        abort_unless(
            $downloadJob->client && $user->hasActiveClientMembership($downloadJob->client),
            403,
        );
    }

    /**
     * @param  Collection<int, Image>  $images
     */
    private function singleClientId(Collection $images): ?int
    {
        $clientIds = $images->pluck('client_id')->unique()->values();

        return $clientIds->count() === 1 ? $clientIds->first() : null;
    }

    private function title(int $count, string $variant, ?string $cropPreset = null): string
    {
        $label = match ($variant) {
            'hd' => 'HD impression',
            'crop' => 'Export '.ImageExportPresets::get((string) $cropPreset)['label'],
            default => 'Web reseaux sociaux',
        };

        return "{$count} image".($count > 1 ? 's' : '')." ({$label})";
    }

    private function downloadDisk(DownloadJob $downloadJob): string
    {
        return $this->imageUrls->disk($downloadJob->storage_provider ?: config('filesystems.image_disk', 'scaleway'));
    }

    private function downloadUrl(DownloadJob $downloadJob): string
    {
        $disk = $this->downloadDisk($downloadJob);

        try {
            return Storage::disk($disk)->temporaryUrl(
                $downloadJob->object_key,
                $downloadJob->download_url_expires_at ?? now()->addMinutes(10),
                ['ResponseContentType' => 'application/zip'],
            );
        } catch (Throwable) {
            return Storage::disk($disk)->url($downloadJob->object_key);
        }
    }
}
