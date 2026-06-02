<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Str;

class ProjectImageStoragePath
{
    public function prefix(Project $project): string
    {
        $projectSegment = $this->segment(
            $project->source_folder ?: $project->slug ?: $project->name ?: "projet-{$project->id}",
        );

        return "photos/{$projectSegment}";
    }

    private function segment(string $value): string
    {
        return Str::slug($value) ?: 'dossier';
    }
}
