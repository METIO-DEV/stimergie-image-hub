<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\SharedAlbum;
use App\Support\ImageUrlResolver;
use App\Support\ObjectStoragePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageAssetController extends Controller
{
    public function __construct(
        private readonly ImageUrlResolver $imageUrls,
        private readonly ObjectStoragePolicy $storagePolicy,
    ) {}

    public function show(Request $request, Image $image)
    {
        abort_unless($request->hasValidSignature(), 403);

        return $this->assetResponse($image, (string) $request->query('variant', 'display'));
    }

    public function sharedAlbum(Request $request, string $shareKey, Image $image)
    {
        abort_unless($request->hasValidSignature(), 403);

        $album = SharedAlbum::query()
            ->where('share_key', $shareKey)
            ->where('is_active', true)
            ->whereHas('images', fn ($query) => $query->whereKey($image->id))
            ->firstOrFail();

        abort_if($album->starts_at && $album->starts_at->isFuture(), 404);
        abort_if($album->expires_at && $album->expires_at->isPast(), 404);
        abort_if($image->rightsAreExpired(), 404);

        return $this->assetResponse($image, (string) $request->query('variant', 'display'));
    }

    private function assetResponse(Image $image, string $variant)
    {
        abort_unless(in_array($variant, ['thumb', 'display', 'web'], true), 404);

        $source = $this->imageUrls->assetSource($image, $variant);
        abort_unless($source['objectKey'], 404);
        abort_if(str_contains($source['objectKey'], 'legacy/'), 404);
        abort_unless(Storage::disk($source['disk'])->exists($source['objectKey']), 404);

        try {
            return redirect()->away(Storage::disk($source['disk'])->temporaryUrl(
                $source['objectKey'],
                now()->addMinutes(ImageUrlResolver::TEMPORARY_ASSET_URL_TTL_MINUTES),
                $this->storagePolicy->temporaryResponseOptions(),
            ));
        } catch (Throwable) {
            return Storage::disk($source['disk'])->response($source['objectKey']);
        }
    }
}
