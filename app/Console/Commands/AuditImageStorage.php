<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\LegacyPhotoObjectKeyResolver;
use App\Support\PhotoBucketIndex;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('images:audit-storage
    {--prefix=photos : Prefixe bucket contenant les photos legacy}
    {--strategy=legacy-url : Strategie de mapping: legacy-url ou legacy-id}
    {--limit= : Limite le nombre d images verifiees objet par objet}
    {--check-bucket : Verifie l existence des objets dans le bucket}
    {--allow-basename-fallback : Autorise le mapping photos/{basename} quand legacy_url ne contient pas /photos/...}')]
#[Description('Audite les references images en base et leur presence dans le bucket Scaleway')]
class AuditImageStorage extends Command
{
    public function handle(LegacyPhotoObjectKeyResolver $objectKeys): int
    {
        $prefix = trim((string) $this->option('prefix'), '/');
        $strategy = (string) $this->option('strategy');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $checkBucket = (bool) $this->option('check-bucket');
        $allowBasenameFallback = (bool) $this->option('allow-basename-fallback');

        $totalImages = Image::count();
        $withOriginal = Image::whereNotNull('object_key_original')->count();
        $withOriginalInPhotos = Image::where('object_key_original', 'like', "{$prefix}/%")->count();
        $withWeb = Image::whereNotNull('object_key_web')->count();
        $withThumb = Image::whereNotNull('object_key_thumb')->count();
        $withHd = Image::whereNotNull('object_key_hd')->count();
        $withStimergieUrls = Image::where(function ($query) {
            $query
                ->where('legacy_url', 'like', '%stimergie.fr%')
                ->orWhere('legacy_thumbnail_url', 'like', '%stimergie.fr%');
        })->count();
        $withBucketUrls = Image::where(function ($query) {
            $query
                ->where('legacy_url', 'like', '%s3.fr-par.scw.cloud/%')
                ->orWhere('legacy_thumbnail_url', 'like', '%s3.fr-par.scw.cloud/%');
        })->count();

        $this->components->twoColumnDetail('Images en base', (string) $totalImages);
        $this->components->twoColumnDetail('Avec object_key_original', (string) $withOriginal);
        $this->components->twoColumnDetail("Avec object_key_original dans {$prefix}/", (string) $withOriginalInPhotos);
        $this->components->twoColumnDetail('Avec object_key_web', (string) $withWeb);
        $this->components->twoColumnDetail('Avec object_key_thumb', (string) $withThumb);
        $this->components->twoColumnDetail('Avec object_key_hd', (string) $withHd);
        $this->components->twoColumnDetail('URLs encore sur stimergie.fr', (string) $withStimergieUrls);
        $this->components->twoColumnDetail('URLs bucket Scaleway', (string) $withBucketUrls);

        $query = Image::query()
            ->whereNotNull('legacy_id')
            ->orderBy('legacy_id');

        if ($limit) {
            $query->limit($limit);
        }

        $checked = 0;
        $mappableToPhotos = 0;
        $unmapped = 0;
        $availableInBucket = 0;
        $missingInBucket = 0;
        $missingExamples = [];
        $disk = $checkBucket ? Storage::disk('scaleway') : null;
        $bucketIndex = $checkBucket && $strategy === 'legacy-url' && ! $allowBasenameFallback
            ? new PhotoBucketIndex($disk->allFiles($prefix))
            : null;

        $query->each(function (Image $image) use (
            $disk,
            $bucketIndex,
            $prefix,
            $strategy,
            $objectKeys,
            $allowBasenameFallback,
            $checkBucket,
            &$checked,
            &$mappableToPhotos,
            &$unmapped,
            &$availableInBucket,
            &$missingInBucket,
            &$missingExamples,
        ): void {
            $checked++;
            $key = $image->object_key_original && str_starts_with($image->object_key_original, "{$prefix}/")
                ? $image->object_key_original
                : $objectKeys->resolve($image, $prefix, $strategy, $allowBasenameFallback);

            if (! $key) {
                $unmapped++;

                return;
            }

            $mappableToPhotos++;

            if (! $checkBucket) {
                return;
            }

            if ($bucketIndex !== null ? $bucketIndex->has($key) : $disk?->exists($key)) {
                $availableInBucket++;

                return;
            }

            $missingInBucket++;

            if (count($missingExamples) < 10) {
                $missingExamples[] = "{$image->id} / legacy {$image->legacy_id} / {$key} / {$image->title}";
            }
        });

        $this->newLine();
        $this->components->twoColumnDetail('Images verifiees', (string) $checked);
        $this->components->twoColumnDetail("Images mappables vers {$prefix}/", (string) $mappableToPhotos);
        $this->components->twoColumnDetail('Mappings impossibles', (string) $unmapped);

        if ($checkBucket) {
            $this->components->twoColumnDetail("Images disponibles dans {$prefix}/", (string) $availableInBucket);
            $this->components->twoColumnDetail('Originaux manquants bucket', (string) $missingInBucket);
        }

        if ($missingExamples !== []) {
            $this->warn('Exemples manquants:');
            foreach ($missingExamples as $example) {
                $this->line("- {$example}");
            }
        }

        return self::SUCCESS;
    }
}
