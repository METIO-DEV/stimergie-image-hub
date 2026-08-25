<?php

namespace App\Support;

use App\Models\Image;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonthlyImageDigestPayload
{
    public function __construct(private readonly ImageUrlResolver $imageUrls) {}

    /**
     * @param  Collection<int, Image>  $images
     * @return array<string, mixed>
     */
    public function build(User $user, Collection $images, Carbon $startsAt, Carbon $endsAt): array
    {
        return [
            'user_name' => $user->name,
            'period_start' => $startsAt->toDateString(),
            'period_end' => $endsAt->toDateString(),
            'image_count' => $images->count(),
            'gallery_url' => route('gallery.index'),
            'projects' => $images
                ->groupBy('project_id')
                ->map(fn (Collection $projectImages) => [
                    'project_name' => $projectImages->first()->project?->name,
                    'client_name' => $projectImages->first()->client?->name,
                    'image_count' => $projectImages->count(),
                    'preview_images' => $projectImages->take(3)->map(fn (Image $image) => [
                        'title' => $image->title,
                        'thumbnail_url' => $this->imageUrls->thumbnailUrl($image),
                        'url' => $this->imageUrls->displayUrl($image),
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'images' => $images->take(20)->map(fn (Image $image) => [
                'title' => $image->title,
                'project_name' => $image->project?->name,
                'client_name' => $image->client?->name,
                'url' => $this->imageUrls->displayUrl($image),
            ])->values()->all(),
        ];
    }
}
