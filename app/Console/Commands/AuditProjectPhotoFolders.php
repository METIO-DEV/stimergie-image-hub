<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\Project;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectImageVariantConvention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('images:audit-project-photo-folders
    {--project= : ID du projet a auditer}
    {--folder= : Dossier source projet a auditer}
    {--limit= : Limite le nombre de projets audites}
    {--risk-limit=150 : Nombre maximum de lignes de risque detaillees}')]
#[Description('Cartographie les dossiers photos projet et les references DB avant migration des variantes')]
class AuditProjectPhotoFolders extends Command
{
    public function handle(ProjectImageVariantConvention $convention): int
    {
        $diskName = (string) config('filesystems.image_disk', 'scaleway');
        $disk = Storage::disk($diskName);
        $photoFiles = $this->files($disk, 'photos');
        $globalImageFiles = $this->files($disk, 'images');
        $existingKeys = array_fill_keys([...$photoFiles, ...$globalImageFiles], true);
        $riskLimit = max(0, (int) $this->option('risk-limit'));
        $riskRows = [];
        $riskCount = 0;
        $totals = [
            'projects' => 0,
            'photo_files' => 0,
            'root_files' => 0,
            'legacy_jpg_files' => 0,
            'web_files' => 0,
            'thumbnail_files' => 0,
            'hd_files' => 0,
            'db_images' => 0,
            'risks' => 0,
        ];

        $projects = Project::query()
            ->with(['client:id,name', 'images.variants'])
            ->when($this->option('project'), fn ($query) => $query->whereKey((int) $this->option('project')))
            ->when($this->option('folder'), fn ($query) => $this->constrainFolder($query, (string) $this->option('folder')))
            ->orderBy('id')
            ->when($this->option('limit'), fn ($query) => $query->limit((int) $this->option('limit')))
            ->get();

        $this->warn('Dry-run: aucune modification objet ou base ne sera effectuee.');
        $this->line('Convention cible: photos/<projet>/web/, photos/<projet>/hd/, photos/<projet>/miniatures/.');
        $this->newLine();

        foreach ($projects as $project) {
            $projectImages = $project->images;
            $prefix = $convention->projectPrefixForImages($project, $projectImages);
            $projectFiles = array_values(array_filter(
                $photoFiles,
                fn (string $key): bool => Str::startsWith($key, "{$prefix}/"),
            ));
            $bucketCounts = $this->bucketCounts($projectFiles, $prefix);
            $dbCounts = $this->dbCounts($projectImages, $convention, $project);

            $totals['projects']++;
            $totals['photo_files'] += count($projectFiles);
            $totals['root_files'] += $bucketCounts['root'];
            $totals['legacy_jpg_files'] += $bucketCounts['legacy_jpg'];
            $totals['web_files'] += $bucketCounts['web'];
            $totals['thumbnail_files'] += $bucketCounts['miniatures'];
            $totals['hd_files'] += $bucketCounts['hd'];
            $totals['db_images'] += $projectImages->count();

            $this->line(sprintf(
                '#%d %s%s - %s',
                $project->id,
                $project->client?->name ? "{$project->client->name} / " : '',
                $project->name,
                $prefix,
            ));
            $this->line(sprintf(
                '  bucket: %d fichier(s), racine %d, JPG legacy %d, web %d, miniatures %d, hd %d',
                count($projectFiles),
                $bucketCounts['root'],
                $bucketCounts['legacy_jpg'],
                $bucketCounts['web'],
                $bucketCounts['miniatures'],
                $bucketCounts['hd'],
            ));
            $this->line(sprintf(
                '  db: %d image(s), original prefix %d, web conforme %d, thumb conforme %d, hd conforme %d, web global %d, thumb global %d',
                $projectImages->count(),
                $dbCounts['original_prefix'],
                $dbCounts['web_conforming'],
                $dbCounts['thumb_conforming'],
                $dbCounts['hd_conforming'],
                $dbCounts['web_global'],
                $dbCounts['thumb_global'],
            ));

            foreach ($this->duplicateBasenames($projectFiles, $prefix) as $basename => $keys) {
                $riskCount++;
                $this->appendRisk($riskRows, $riskLimit, [
                    'project_id' => $project->id,
                    'image_id' => null,
                    'risk' => 'duplicate_filename',
                    'object_key_original' => null,
                    'object_key_web' => null,
                    'object_key_thumb' => null,
                    'object_key_hd' => null,
                    'action' => 'verifier le mapping: '.$basename.' present '.count($keys).' fois',
                ]);
            }

            foreach ($projectImages as $image) {
                foreach ($this->imageRisks($image, $existingKeys, $convention) as $risk) {
                    $riskCount++;
                    $this->appendRisk($riskRows, $riskLimit, $risk);
                }
            }
        }

        $totals['risks'] = $riskCount;

        $this->newLine();
        $this->components->twoColumnDetail('Projets audites', (string) $totals['projects']);
        $this->components->twoColumnDetail('Fichiers photos vus', (string) $totals['photo_files']);
        $this->components->twoColumnDetail('Fichiers racine', (string) $totals['root_files']);
        $this->components->twoColumnDetail('Fichiers JPG legacy', (string) $totals['legacy_jpg_files']);
        $this->components->twoColumnDetail('Fichiers web', (string) $totals['web_files']);
        $this->components->twoColumnDetail('Fichiers miniatures', (string) $totals['thumbnail_files']);
        $this->components->twoColumnDetail('Fichiers hd', (string) $totals['hd_files']);
        $this->components->twoColumnDetail('Images DB auditees', (string) $totals['db_images']);
        $this->components->twoColumnDetail('Risques detectes', (string) $totals['risks']);

        if ($riskRows !== []) {
            $this->newLine();
            $this->warn('Cas a risque:');
            $this->table(
                ['project_id', 'image_id', 'risk', 'object_key_original', 'object_key_web', 'object_key_thumb', 'object_key_hd', 'action'],
                $riskRows,
            );

            if ($riskCount > count($riskRows)) {
                $this->warn(($riskCount - count($riskRows)).' risque(s) non affiches par --risk-limit.');
            }
        }

        return self::SUCCESS;
    }

    private function constrainFolder($query, string $folder): void
    {
        $folder = trim($folder, '/');

        $query->where(function ($query) use ($folder): void {
            $query
                ->where('source_folder', $folder)
                ->orWhere('name', $folder)
                ->orWhere('slug', $folder);
        });
    }

    /**
     * @return array<int, string>
     */
    private function files($disk, string $prefix): array
    {
        return collect($disk->allFiles($prefix))
            ->map(fn (string $key): string => trim($key, '/'))
            ->filter(fn (string $key): bool => $this->isImageObject($key))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $files
     * @return array{root: int, legacy_jpg: int, web: int, miniatures: int, hd: int}
     */
    private function bucketCounts(array $files, string $prefix): array
    {
        $counts = [
            'root' => 0,
            'legacy_jpg' => 0,
            'web' => 0,
            'miniatures' => 0,
            'hd' => 0,
        ];

        foreach ($files as $key) {
            $relative = Str::after($key, rtrim($prefix, '/').'/');
            $segments = explode('/', $relative);
            $firstSegment = Str::lower($segments[0] ?? '');

            if (count($segments) === 1) {
                $counts['root']++;
            }

            if ($firstSegment === 'jpg' || str_contains(Str::lower($relative), '/jpg/')) {
                $counts['legacy_jpg']++;
            }

            if ($firstSegment === ImageVariantGenerator::WEB_VARIANT_DIRECTORY) {
                $counts['web']++;
            }

            if ($firstSegment === ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY) {
                $counts['miniatures']++;
            }

            if ($firstSegment === ImageVariantGenerator::HD_VARIANT_DIRECTORY) {
                $counts['hd']++;
            }
        }

        return $counts;
    }

    private function dbCounts($images, ProjectImageVariantConvention $convention, Project $project): array
    {
        $prefix = $convention->projectPrefixForImages($project, $images);

        return [
            'original_prefix' => $images->filter(fn (Image $image): bool => Str::startsWith((string) $image->object_key_original, "{$prefix}/"))->count(),
            'web_conforming' => $images->filter(fn (Image $image): bool => $convention->isConformingWebKey($image, $image->object_key_web))->count(),
            'thumb_conforming' => $images->filter(fn (Image $image): bool => $convention->isConformingThumbnailKey($image, $image->object_key_thumb))->count(),
            'hd_conforming' => $images->filter(fn (Image $image): bool => $convention->isConformingHdKey($image, $image->object_key_hd) && $convention->isConformingHdKey($image, $image->object_key_original))->count(),
            'web_global' => $images->filter(fn (Image $image): bool => Str::startsWith((string) $image->object_key_web, 'images/'))->count(),
            'thumb_global' => $images->filter(fn (Image $image): bool => Str::startsWith((string) $image->object_key_thumb, 'images/'))->count(),
        ];
    }

    /**
     * @param  array<int, string>  $files
     * @return array<string, array<int, string>>
     */
    private function duplicateBasenames(array $files, string $prefix): array
    {
        $byName = [];

        foreach ($files as $key) {
            $relative = Str::after($key, rtrim($prefix, '/').'/');
            $firstSegment = Str::lower(strtok($relative, '/') ?: '');

            if (in_array($firstSegment, ['jpg', ImageVariantGenerator::WEB_VARIANT_DIRECTORY, ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY, ImageVariantGenerator::HD_VARIANT_DIRECTORY], true)) {
                continue;
            }

            $byName[Str::lower(basename($key))][] = $key;
        }

        return array_filter($byName, fn (array $keys): bool => count($keys) > 1);
    }

    /**
     * @param  array<string, true>  $existingKeys
     * @return array<int, array<string, mixed>>
     */
    private function imageRisks(Image $image, array $existingKeys, ProjectImageVariantConvention $convention): array
    {
        $risks = [];

        if ($image->object_key_web && ! $convention->isConformingWebKey($image, $image->object_key_web)) {
            $risks[] = $this->riskRow($image, 'web_not_conforming', 'migrer vers web/ projet ou regenerer');
        }

        if ($image->object_key_thumb && ! $convention->isConformingThumbnailKey($image, $image->object_key_thumb)) {
            $risks[] = $this->riskRow($image, 'thumb_not_conforming', 'migrer vers miniatures/ projet ou regenerer');
        }

        if (
            $image->object_key_hd
            && (
                ! $convention->isConformingHdKey($image, $image->object_key_hd)
                || ! $convention->isConformingHdKey($image, $image->object_key_original)
            )
        ) {
            $risks[] = $this->riskRow($image, 'hd_not_conforming', 'migrer vers hd/ projet depuis la source lourde');
        }

        if ($image->object_key_web && in_array($image->object_key_web, array_filter([$image->object_key_original, $image->object_key_hd]), true)) {
            $risks[] = $this->riskRow($image, 'web_points_to_original', 'generer une variante web projet');
        }

        if ($image->object_key_thumb && $image->object_key_thumb === $image->object_key_web) {
            $risks[] = $this->riskRow($image, 'thumb_points_to_web', 'generer une vraie miniature projet');
        }

        foreach (['object_key_original', 'object_key_web', 'object_key_thumb', 'object_key_hd'] as $column) {
            $key = $image->{$column};

            if (! is_string($key) || $key === '' || (! Str::startsWith($key, 'photos/') && ! Str::startsWith($key, 'images/'))) {
                continue;
            }

            if (! isset($existingKeys[$key])) {
                $risks[] = $this->riskRow($image, "missing_{$column}", 'verifier objet source ou regenerer');
            }
        }

        return $risks;
    }

    private function riskRow(Image $image, string $risk, string $action): array
    {
        return [
            'project_id' => $image->project_id,
            'image_id' => $image->id,
            'risk' => $risk,
            'object_key_original' => $image->object_key_original,
            'object_key_web' => $image->object_key_web,
            'object_key_thumb' => $image->object_key_thumb,
            'object_key_hd' => $image->object_key_hd,
            'action' => $action,
        ];
    }

    private function appendRisk(array &$rows, int $limit, array $row): void
    {
        if ($limit === 0 || count($rows) < $limit) {
            $rows[] = $row;
        }
    }

    private function isImageObject(string $key): bool
    {
        $filename = basename($key);

        if ($filename === '' || Str::startsWith($filename, '.')) {
            return false;
        }

        return in_array(Str::lower(pathinfo($key, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
