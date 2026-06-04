<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImageClientShareRequest;
use App\Models\Client;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageClientShareController extends Controller
{
    public function index(Request $request, Image $image): JsonResponse
    {
        $this->authorizeManageImage($request, $image);

        return response()->json([
            'sharedClients' => $this->sharedClients($image),
        ]);
    }

    public function store(StoreImageClientShareRequest $request, Image $image): JsonResponse
    {
        $data = $request->validated();

        $image->sharedClients()->syncWithoutDetaching([
            (int) $data['client_id'] => [
                'created_by' => $request->user()->id,
                'expires_at' => $data['expires_at'] ?? null,
            ],
        ]);

        return response()->json([
            'sharedClients' => $this->sharedClients($image->fresh()),
        ], 201);
    }

    public function destroy(Request $request, Image $image, Client $client): JsonResponse
    {
        $this->authorizeManageImage($request, $image);

        $image->sharedClients()->detach($client->id);

        return response()->json([
            'sharedClients' => $this->sharedClients($image->fresh()),
        ]);
    }

    private function authorizeManageImage(Request $request, Image $image): void
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin()
            || ($image->client && $user?->hasClientRole($image->client, ['owner', 'manager'])), 403);
    }

    /**
     * @return array<int, array{id: int, name: string, expiresAt: string|null}>
     */
    private function sharedClients(Image $image): array
    {
        return $image->sharedClients()
            ->orderBy('name')
            ->get(['clients.id', 'clients.name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'expiresAt' => $client->pivot->expires_at,
            ])
            ->values()
            ->all();
    }
}
