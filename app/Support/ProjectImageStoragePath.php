<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Str;

class ProjectImageStoragePath
{
    public function prefix(Project $project): string
    {
        $projectSegment = $project->source_folder
            ? $this->sourceFolderSegment($project->source_folder)
            : $this->slugSegment($project->slug ?: $project->name ?: "projet-{$project->id}");

        return "photos/{$projectSegment}";
    }

    private function sourceFolderSegment(string $value): string
    {
        $value = str_replace('\\', '/', $value);
        $value = preg_replace('#/+#', '/', $value);
        $value = trim((string) $value, "/ \t\n\r\0\x0B");

        return $value !== '' ? $value : 'dossier';
    }

    private function slugSegment(string $value): string
    {
        return Str::slug($value) ?: 'dossier';
    }
}
