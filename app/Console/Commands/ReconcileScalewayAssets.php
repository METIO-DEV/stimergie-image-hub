<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\LegacyPhotoObjectKeyResolver;
use App\Support\PhotoBucketIndex;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('images:reconcile-scaleway-assets
    {--prefix=photos : Prefixe bucket contenant les photos legacy}
    {--strategy=legacy-url : Strategie de mapping: legacy-url ou legacy-id}
    {--limit= : Limite le nombre d images traitees}
    {--dry-run : Affiche les changements sans modifier la base}
    {--force : Reecrit object_key_original meme si elle est deja renseignee}
    {--allow-basename-fallback : Autorise le mapping photos/{basename} quand legacy_url ne contient pas /photos/...}')]
#[Description('Rapproche les images en base avec les objets deja presents dans Scaleway')]
class ReconcileScalewayAssets extends Command
{
    public function handle(LegacyPhotoObjectKeyResolver $objectKeys): int
    {
        $prefix = trim((string) $this->option('prefix'), '/');
        $strategy = (string) $this->option('strategy');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $allowBasenameFallback = (bool) $this->option('allow-basename-fallback');
        $disk = Storage::disk('scaleway');
        $bucketIndex = $this->bucketIndex($disk, $prefix, $strategy, $allowBasenameFallback);

        $query = Image::query()
            ->whereNotNull('legacy_id')
            ->when(! $force, fn ($query) => $query->where(function ($query): void {
                $query
                    ->whereNull('object_key_original')
                    ->orWhere('object_key_original', 'like', 'legacy/images/%');
            }))
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $checked = 0;
        $updated = 0;
        $missing = 0;
        $unmapped = 0;
        $urlsRewritten = 0;

        $query->lazyById()->each(function (Image $image) use (
            $disk,
            $prefix,
            $strategy,
            $objectKeys,
            $allowBasenameFallback,
            $bucketIndex,
            $dryRun,
            &$checked,
            &$updated,
            &$missing,
            &$unmapped,
            &$urlsRewritten,
        ): void {
            $checked++;
            $objectKey = $objectKeys->resolve($image, $prefix, $strategy, $allowBasenameFallback);

            if (! $objectKey) {
                $unmapped++;
                $this->warn("Mapping impossible: image {$image->id} legacy {$image->legacy_id} ({$image->title})");

                return;
            }

            $resolvedObjectKey = $this->resolveObjectKey($disk, $objectKey, $bucketIndex);

            if (! $resolvedObjectKey) {
                $missing++;
                $this->warn("Objet absent: {$objectKey} ({$image->title})");

                if (! $dryRun) {
                    $publicUrl = $disk->url($objectKey);

                    $image->update([
                        'legacy_url' => $publicUrl,
                        'legacy_thumbnail_url' => $publicUrl,
                    ]);

                    $urlsRewritten++;
                }

                return;
            }

            $webObjectKey = $this->resolveVariantObjectKey($disk, $objectKey, $bucketIndex, 'web') ?? $resolvedObjectKey;
            $hdObjectKey = $this->resolveVariantObjectKey($disk, $objectKey, $bucketIndex, 'hd') ?? $resolvedObjectKey;

            if ($dryRun) {
                $updated++;
                $this->line("[dry-run] {$image->id} legacy {$image->legacy_id} -> HD {$hdObjectKey} / web {$webObjectKey}");

                return;
            }

            $publicUrl = $disk->url($hdObjectKey);
            $thumbnailUrl = $disk->url($webObjectKey);

            $image->update([
                'storage_provider' => 'scaleway',
                'object_key_original' => $hdObjectKey,
                'object_key_web' => $webObjectKey,
                'object_key_thumb' => $webObjectKey,
                'object_key_hd' => $hdObjectKey,
                'legacy_url' => $publicUrl,
                'legacy_thumbnail_url' => $thumbnailUrl,
                'processing_error' => null,
                'processed_at' => $image->processed_at ?? now(),
                'status' => $image->status === 'pending_upload' ? 'ready' : $image->status,
            ]);

            $updated++;
            $urlsRewritten++;

            if ($updated % 100 === 0) {
                $this->info("Images rapprochees: {$updated}");
            }
        });

        $this->newLine();
        $this->components->twoColumnDetail('Images verifiees', (string) $checked);
        $this->components->twoColumnDetail($dryRun ? 'Images a rapprocher' : 'Images rapprochees', (string) $updated);
        $this->components->twoColumnDetail('Mappings impossibles', (string) $unmapped);
        $this->components->twoColumnDetail('Objets manquants', (string) $missing);
        $this->components->twoColumnDetail($dryRun ? 'URLs a reecrire' : 'URLs reecrites', (string) $urlsRewritten);

        return self::SUCCESS;
    }

    private function bucketIndex($disk, string $prefix, string $strategy, bool $allowBasenameFallback): ?PhotoBucketIndex
    {
        if ($strategy !== 'legacy-url' || $allowBasenameFallback) {
            return null;
        }

        $this->info("Chargement de l index bucket {$prefix}/...");

        return new PhotoBucketIndex($disk->allFiles($prefix));
    }

    private function resolveObjectKey($disk, string $objectKey, ?PhotoBucketIndex $bucketIndex): ?string
    {
        if ($bucketIndex !== null) {
            return $bucketIndex->resolve($objectKey);
        }

        return $disk->exists($objectKey) ? $objectKey : null;
    }

    private function resolveVariantObjectKey($disk, string $objectKey, ?PhotoBucketIndex $bucketIndex, string $variant): ?string
    {
        if ($bucketIndex !== null) {
            return $bucketIndex->resolveVariant($objectKey, $variant);
        }

        return $disk->exists($objectKey) ? $objectKey : null;
    }
}
