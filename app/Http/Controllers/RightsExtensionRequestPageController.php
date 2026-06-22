<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RightsExtensionRequestPageController extends Controller
{
    public function __construct(
        private readonly ProjectAccess $projectAccess,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $clientIds = $this->projectAccess->accessibleClientIds($user);

        return Inertia::render('RightsExtensions/Index', [
            'requests' => $this->requestSummaries($clientIds),
        ]);
    }

    /**
     * @param  array<int>|null  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function requestSummaries(?array $clientIds): array
    {
        $requests = ImageRightsExtensionRequest::query()
            ->with([
                'image:id,title',
                'client:id,name',
                'project:id,name',
                'requester:id,name,email',
                'resolver:id,name,email',
            ])
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (ImageRightsExtensionRequest $request) => [
                'id' => $request->id,
                'status' => $request->status,
                'statusLabel' => $request->statusLabel(),
                'imageId' => $request->image_id,
                'imageTitle' => $request->image?->title,
                'clientName' => $request->client?->name,
                'projectName' => $request->project?->name,
                'rightsEndsAt' => $request->rights_ends_at?->toDateString(),
                'requestedBy' => $request->requester?->name ?: $request->requester?->email,
                'requestedAt' => $request->created_at?->toIso8601String(),
                'resolvedBy' => $request->resolver?->name ?: $request->resolver?->email,
                'resolvedAt' => $request->resolved_at?->toIso8601String(),
                'isLegacy' => false,
            ])
            ->values();

        $requestImageIds = $requests
            ->pluck('imageId')
            ->filter()
            ->all();

        $legacyRequests = Image::query()
            ->with([
                'client:id,name',
                'project:id,name',
                'rightsExtensionRequester:id,name,email',
            ])
            ->whereNotNull('rights_extension_requested_at')
            ->when($requestImageIds !== [], fn ($query) => $query->whereNotIn('id', $requestImageIds))
            ->when($clientIds !== null, fn ($query) => $query->whereIn('client_id', $clientIds))
            ->latest('rights_extension_requested_at')
            ->limit(80)
            ->get()
            ->map(fn (Image $image) => [
                'id' => "legacy-image-{$image->id}",
                'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
                'statusLabel' => 'Demandée',
                'imageId' => $image->id,
                'imageTitle' => $image->title,
                'clientName' => $image->client?->name,
                'projectName' => $image->project?->name,
                'rightsEndsAt' => $image->rights_ends_at?->toDateString(),
                'requestedBy' => $image->rightsExtensionRequester?->name ?: $image->rightsExtensionRequester?->email,
                'requestedAt' => $image->rights_extension_requested_at?->toIso8601String(),
                'resolvedBy' => null,
                'resolvedAt' => null,
                'isLegacy' => true,
            ]);

        return $requests
            ->concat($legacyRequests)
            ->sortByDesc('requestedAt')
            ->take(80)
            ->values()
            ->all();
    }
}
