<?php

namespace App\Support;

use App\Models\Image;
use App\Models\Project;
use Illuminate\Support\Str;

class ProjectImageVariantConvention
{
    public function __construct(private readonly ProjectImageStoragePath $storagePath) {}

    public function projectPrefix(Project $project): string
    {
        return $this->storagePath->prefix($project);
    }

    public function projectPrefixForImage(Image $image, ?string $sourceKey = null): string
    {
        foreach ([$sourceKey, $image->object_key_original, $image->object_key_hd, $image->object_key_web, $image->object_key_thumb] as $objectKey) {
            $prefix = $this->projectRootFromObjectKey($objectKey);

            if ($prefix) {
                return $prefix;
            }
        }

        return $image->project instanceof Project
            ? $this->projectPrefix($image->project)
            : trim((string) dirname((string) ($image->object_key_original ?: $sourceKey)), '/');
    }

    public function projectPrefixForImages(Project $project, iterable $images): string
    {
        foreach ($images as $image) {
            if ($image instanceof Image) {
                $prefix = $this->projectPrefixForImage($image);

                if ($prefix !== '') {
                    return $prefix;
                }
            }
        }

        return $this->projectPrefix($project);
    }

    public function webPrefix(Project $project): string
    {
        return $this->projectPrefix($project).'/'.ImageVariantGenerator::WEB_VARIANT_DIRECTORY;
    }

    public function thumbnailPrefix(Project $project): string
    {
        return $this->projectPrefix($project).'/'.ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY;
    }

    public function hdPrefix(Project $project): string
    {
        return $this->projectPrefix($project).'/'.ImageVariantGenerator::HD_VARIANT_DIRECTORY;
    }

    public function targetKey(Image $image, string $kind, ?string $sourceKey = null): string
    {
        $project = $image->project;

        $projectPrefix = $this->projectPrefixForImage($image, $sourceKey);
        $prefix = match ($kind) {
            'thumb' => $projectPrefix.'/'.ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY,
            'hd', 'original' => $projectPrefix.'/'.ImageVariantGenerator::HD_VARIANT_DIRECTORY,
            default => $projectPrefix.'/'.ImageVariantGenerator::WEB_VARIANT_DIRECTORY,
        };

        $extension = $this->extension($sourceKey ?: $image->object_key_original ?: $image->object_key_web ?: 'image.jpg');

        return trim($prefix, '/')."/{$image->id}.{$extension}";
    }

    public function isConformingWebKey(Image $image, ?string $objectKey): bool
    {
        if (! $this->isUsableVariantKey($image, $objectKey)) {
            return false;
        }

        return $this->isInProjectVariantDirectory($image, $objectKey, ImageVariantGenerator::WEB_VARIANT_DIRECTORY);
    }

    public function isConformingThumbnailKey(Image $image, ?string $objectKey): bool
    {
        if (! $this->isUsableVariantKey($image, $objectKey)) {
            return false;
        }

        return $this->isInProjectVariantDirectory($image, $objectKey, ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY);
    }

    public function isConformingHdKey(Image $image, ?string $objectKey): bool
    {
        if (! is_string($objectKey) || trim($objectKey) === '') {
            return false;
        }

        return $this->isInProjectVariantDirectory($image, $objectKey, ImageVariantGenerator::HD_VARIANT_DIRECTORY);
    }

    public function isUsableVariantKey(Image $image, ?string $objectKey): bool
    {
        if (! is_string($objectKey) || trim($objectKey) === '') {
            return false;
        }

        return ! in_array($objectKey, array_filter([
            $image->object_key_original,
            $image->object_key_hd,
        ]), true);
    }

    public function disk(?string $storageProvider): string
    {
        return match ($storageProvider) {
            'public' => 'public',
            'local' => 'local',
            default => 'scaleway',
        };
    }

    private function isInProjectVariantDirectory(Image $image, string $objectKey, string $directory): bool
    {
        if (! $image->project instanceof Project) {
            return false;
        }

        $objectRoot = $this->projectRootFromObjectKey($objectKey);

        if (! $objectRoot || $objectRoot !== $this->projectPrefixForImage($image)) {
            return false;
        }

        $relative = Str::after($objectKey, rtrim($objectRoot, '/').'/');
        $firstSegment = Str::lower(strtok($relative, '/') ?: '');

        return $firstSegment === $directory;
    }

    public function projectRootFromObjectKey(?string $objectKey): ?string
    {
        if (! is_string($objectKey) || ! Str::startsWith($objectKey, 'photos/')) {
            return null;
        }

        $directory = trim(dirname($objectKey), '/');

        while ($directory !== '' && $directory !== '.' && in_array(Str::lower(basename($directory)), [
            'jpg',
            ImageVariantGenerator::WEB_VARIANT_DIRECTORY,
            ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY,
            ImageVariantGenerator::HD_VARIANT_DIRECTORY,
            'thumbs',
        ], true)) {
            $directory = trim(dirname($directory), '/');
        }

        return $directory !== '' && $directory !== '.' ? $directory : null;
    }

    private function extension(string $objectKey): string
    {
        $extension = Str::lower(pathinfo($objectKey, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}
