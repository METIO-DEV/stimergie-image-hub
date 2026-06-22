<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\ImageVariant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('images:decommission-global-prefix
    {--limit= : Limite le nombre d objets images/ listes ou supprimes}
    {--execute : Supprime les objets du prefixe images/. Dry-run par defaut}')]
#[Description('Audite puis supprime le prefixe global images/ uniquement quand plus aucune reference DB ne l utilise')]
class DecommissionGlobalImagePrefix extends Command
{
    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $references = $this->globalReferences();
        $referenceCount = count($references);
        $disk = Storage::disk((string) config('filesystems.image_disk', 'scaleway'));
        $objects = collect($disk->allFiles('images'))
            ->map(fn (string $key): string => trim($key, '/'))
            ->filter(fn (string $key): bool => Str::startsWith($key, 'images/') && ! str_ends_with($key, '/'))
            ->when($limit !== null, fn ($collection) => $collection->take($limit))
            ->values();

        if (! $execute) {
            $this->warn('Dry-run: aucun objet ne sera supprime. Ajoutez --execute pour appliquer.');
        }

        $this->components->twoColumnDetail('References DB vers images/', (string) $referenceCount);
        $this->components->twoColumnDetail('Objets images/ '.($execute ? 'a supprimer' : 'detectes'), (string) $objects->count());

        if ($references !== []) {
            $this->newLine();
            $this->warn('Suppression bloquee: references DB actives vers images/.');
            $this->table(['table', 'id', 'column', 'object_key'], array_slice($references, 0, 150));

            if ($referenceCount > 150) {
                $this->warn(($referenceCount - 150).' reference(s) non affichees.');
            }

            return $execute ? self::FAILURE : self::SUCCESS;
        }

        if ($objects->isEmpty()) {
            $this->info('Aucun objet sous images/.');

            return self::SUCCESS;
        }

        foreach ($objects as $objectKey) {
            if ($execute) {
                $disk->delete($objectKey);
                $this->info("[deleted] {$objectKey}");
            } else {
                $this->line("[dry-run] supprimer {$objectKey}");
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail($execute ? 'Objets supprimes' : 'Objets supprimables', (string) $objects->count());

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{table: string, id: int, column: string, object_key: string}>
     */
    private function globalReferences(): array
    {
        $references = [];

        foreach (['object_key_original', 'object_key_web', 'object_key_thumb', 'object_key_hd'] as $column) {
            Image::query()
                ->where($column, 'like', 'images/%')
                ->orderBy('id')
                ->get(['id', $column])
                ->each(function (Image $image) use (&$references, $column): void {
                    $references[] = [
                        'table' => 'images',
                        'id' => $image->id,
                        'column' => $column,
                        'object_key' => (string) $image->{$column},
                    ];
                });
        }

        ImageVariant::query()
            ->where('object_key', 'like', 'images/%')
            ->orderBy('id')
            ->get(['id', 'object_key'])
            ->each(function (ImageVariant $variant) use (&$references): void {
                $references[] = [
                    'table' => 'image_variants',
                    'id' => $variant->id,
                    'column' => 'object_key',
                    'object_key' => $variant->object_key,
                ];
            });

        return $references;
    }
}
