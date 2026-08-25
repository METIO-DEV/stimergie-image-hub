<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\Project;
use App\Support\ProjectImageVariantConvention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('images:clean-project-legacy-jpg
    {--project= : ID du projet a nettoyer}
    {--folder= : Dossier source projet a nettoyer}
    {--execute : Applique les mises a jour DB et suppressions objet. Dry-run par defaut}')]
#[Description('Supprime le dossier JPG legacy d un projet quand web/HD ont ete migres')]
class CleanProjectLegacyJpgFolder extends Command
{
    public function handle(ProjectImageVariantConvention $convention): int
    {
        $execute = (bool) $this->option('execute');
        $diskName = (string) config('filesystems.image_disk', 'scaleway');
        $disk = Storage::disk($diskName);
        $totals = [
            'projects' => 0,
            'thumbs_cleared' => 0,
            'objects_deleted' => 0,
            'objects_kept' => 0,
        ];

        if (! $execute) {
            $this->warn('Dry-run: aucune mise a jour DB ni suppression objet. Ajoutez --execute pour appliquer.');
        }

        $projects = Project::query()
            ->with(['images.variants'])
            ->when($this->option('project'), fn ($query) => $query->whereKey((int) $this->option('project')))
            ->when($this->option('folder'), fn ($query) => $this->constrainFolder($query, (string) $this->option('folder')))
            ->orderBy('id')
            ->get();

        foreach ($projects as $project) {
            $totals['projects']++;
            $prefix = $convention->projectPrefixForImages($project, $project->images);
            $legacyJpgPrefix = "{$prefix}/JPG";
            $legacyJpgFiles = collect($disk->allFiles($legacyJpgPrefix))
                ->map(fn (string $key): string => trim($key, '/'))
                ->filter(fn (string $key): bool => $this->isImageObject($key))
                ->values();

            $this->line("#{$project->id} {$project->name} - {$legacyJpgPrefix} ({$legacyJpgFiles->count()} fichier(s))");

            foreach ($project->images as $image) {
                $thumbKey = $image->object_key_thumb;

                if (! is_string($thumbKey) || ! Str::startsWith($thumbKey, "{$legacyJpgPrefix}/")) {
                    continue;
                }

                if ($execute) {
                    DB::transaction(function () use ($image): void {
                        $image->forceFill(['object_key_thumb' => null])->save();
                        $image->variants()->where('kind', 'thumb')->delete();
                    });
                    $this->info("[db-updated] image {$image->id}: object_key_thumb vide");
                } else {
                    $this->line("[dry-run] image {$image->id}: vider object_key_thumb {$thumbKey}");
                }

                $totals['thumbs_cleared']++;
            }

            foreach ($legacyJpgFiles as $objectKey) {
                if ($this->isReferencedObjectKey($objectKey)) {
                    $totals['objects_kept']++;
                    $this->line("[kept] encore reference: {$objectKey}");

                    continue;
                }

                if ($execute) {
                    $disk->delete($objectKey);
                    $this->info("[deleted] {$objectKey}");
                } else {
                    $this->line("[dry-run] supprimer {$objectKey}");
                }

                $totals['objects_deleted']++;
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Projets traites', (string) $totals['projects']);
        $this->components->twoColumnDetail($execute ? 'References thumb videes' : 'References thumb a vider', (string) $totals['thumbs_cleared']);
        $this->components->twoColumnDetail($execute ? 'Objets JPG supprimes' : 'Objets JPG supprimables', (string) $totals['objects_deleted']);
        $this->components->twoColumnDetail('Objets JPG conserves', (string) $totals['objects_kept']);

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

    private function isReferencedObjectKey(string $objectKey): bool
    {
        return Image::query()
            ->where(function ($query) use ($objectKey): void {
                $query
                    ->where('object_key_original', $objectKey)
                    ->orWhere('object_key_web', $objectKey)
                    ->orWhere('object_key_thumb', $objectKey)
                    ->orWhere('object_key_hd', $objectKey)
                    ->orWhereHas('variants', fn ($query) => $query->where('object_key', $objectKey));
            })
            ->exists();
    }

    private function isImageObject(string $key): bool
    {
        if ($key === '' || str_ends_with($key, '/') || str_starts_with(basename($key), '.')) {
            return false;
        }

        return in_array(Str::lower(pathinfo($key, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
