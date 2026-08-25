<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Support\ProjectBucketImageSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('images:sync-project-bucket-assets
    {--project= : ID du projet a synchroniser}
    {--limit= : Nombre maximum de projets a traiter}
    {--dry-run : Compte les images qui seraient creees sans modifier la base}')]
#[Description('Cree les images manquantes en base depuis les dossiers photos des projets dans Scaleway')]
class SyncProjectBucketAssets extends Command
{
    public function handle(ProjectBucketImageSynchronizer $bucketImages): int
    {
        $projectId = $this->option('project') ? (int) $this->option('project') : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = Project::query()
            ->with('client:id,name')
            ->orderBy('id');

        if ($projectId) {
            $query->whereKey($projectId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->warn('Aucun projet a synchroniser.');

            return self::SUCCESS;
        }

        $totals = [
            'projects' => 0,
            'bucket_images' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($projects as $project) {
            $result = $bucketImages->sync($project, null, $dryRun);

            $totals['projects']++;
            $totals['bucket_images'] += $result['total'];
            $totals['created'] += $result['created'];
            $totals['updated'] += $result['updated'];
            $totals['skipped'] += $result['skipped'];

            $this->line(sprintf(
                '#%d %s%s - %s : total %d, %s %d, maj %d, ignores %d',
                $project->id,
                $project->client?->name ? "{$project->client->name} / " : '',
                $project->name,
                $result['prefix'],
                $result['total'],
                $dryRun ? 'a creer' : 'creees',
                $result['created'],
                $result['updated'],
                $result['skipped'],
            ));
        }

        $this->newLine();
        $this->components->twoColumnDetail('Projets traites', (string) $totals['projects']);
        $this->components->twoColumnDetail('Images trouvees dans les buckets projet', (string) $totals['bucket_images']);
        $this->components->twoColumnDetail($dryRun ? 'Images a creer' : 'Images creees', (string) $totals['created']);
        $this->components->twoColumnDetail('Images mises a jour', (string) $totals['updated']);
        $this->components->twoColumnDetail('Images ignorees', (string) $totals['skipped']);

        return self::SUCCESS;
    }
}
