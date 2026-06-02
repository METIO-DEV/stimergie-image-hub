<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Str;

class ProjectImageStoragePath
{
    public function prefix(Project $project): string
    {
        $project->loadMissing('client:id,name,slug');

        $clientSegment = $this->segment(
            $project->client?->slug ?: $project->client?->name ?: "client-{$project->client_id}",
        );
        $projectSegment = $this->segment(
            $project->source_folder ?: $project->slug ?: $project->name ?: "projet-{$project->id}",
        );

        return "photos/{$clientSegment}/{$projectSegment}";
    }

    private function segment(string $value): string
    {
        return Str::slug($value) ?: 'dossier';
    }
}
