<?php

namespace App\Support;

class ImageExportPresets
{
    /**
     * @return array<string, array{label: string, slug: string, width: int, height: int}>
     */
    public static function all(): array
    {
        return [
            'square' => [
                'label' => 'Carré 1:1',
                'slug' => 'carre-1-1',
                'width' => 1600,
                'height' => 1600,
            ],
            'story' => [
                'label' => 'Story 9:16',
                'slug' => 'story-9-16',
                'width' => 1080,
                'height' => 1920,
            ],
            'magazine' => [
                'label' => 'Magazine 4:3',
                'slug' => 'magazine-4-3',
                'width' => 1600,
                'height' => 1200,
            ],
            'web_banner' => [
                'label' => 'Bandeau web ultra-large',
                'slug' => 'bandeau-web-3-1',
                'width' => 2400,
                'height' => 800,
            ],
        ];
    }

    /**
     * @return array{label: string, slug: string, width: int, height: int}
     */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? self::all()['square'];
    }
}
