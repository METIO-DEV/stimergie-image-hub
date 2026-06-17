<?php

namespace App\Support;

use App\Models\AssetFolderMapping;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectFolderMatcher
{
    public const AUTO_MATCH_SCORE = 92.0;

    public const SUGGESTION_SCORE = 70.0;

    /**
     * @param  Collection<int, Project>|null  $projects
     * @return array{project: Project|null, score: float, source: string|null}
     */
    public function bestProject(string $folder, ?Collection $projects = null): array
    {
        $projects ??= Project::query()->get();
        $normalizedFolder = $this->normalize($folder);
        $bestProject = null;
        $bestScore = 0.0;
        $bestSource = null;

        foreach ($projects as $project) {
            foreach ($this->projectFolderCandidates($project) as $candidate) {
                $score = $this->score($normalizedFolder, $this->normalize($candidate));

                if ($score > $bestScore) {
                    $bestProject = $project;
                    $bestScore = $score;
                    $bestSource = $candidate;
                }
            }
        }

        return [
            'project' => $bestProject,
            'score' => $bestScore,
            'source' => $bestSource,
        ];
    }

    /**
     * @param  Collection<int, Project>|null  $projects
     */
    public function exactProject(string $folder, ?Collection $projects = null): ?Project
    {
        $projects ??= Project::query()->get();
        $normalizedFolder = $this->normalize($folder);

        return $projects->first(function (Project $project) use ($normalizedFolder): bool {
            foreach ($this->projectFolderCandidates($project) as $candidate) {
                if ($this->normalize($candidate) === $normalizedFolder) {
                    return true;
                }
            }

            return false;
        });
    }

    public function mappedProject(string $folder): ?Project
    {
        $mapping = AssetFolderMapping::query()
            ->with('project')
            ->where('folder', $folder)
            ->where('status', 'mapped')
            ->first();

        return $mapping?->project;
    }

    public function ignored(string $folder): bool
    {
        return AssetFolderMapping::query()
            ->where('folder', $folder)
            ->where('status', 'ignored')
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    public function mappedFoldersForProject(Project $project): array
    {
        return AssetFolderMapping::query()
            ->where('project_id', $project->id)
            ->where('status', 'mapped')
            ->pluck('folder')
            ->map(fn ($folder) => (string) $folder)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function projectFolderCandidates(Project $project): array
    {
        $metadata = $project->metadata ?? [];
        $aliases = is_array($metadata['source_folder_aliases'] ?? null)
            ? $metadata['source_folder_aliases']
            : [];

        return collect([
            $project->source_folder,
            $project->slug,
            $project->name,
            ...$aliases,
            ...$this->mappedFoldersForProject($project),
        ])
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    public function normalize(string $value): string
    {
        $value = basename(str_replace('\\', '/', $value));
        $value = Str::ascii($value);
        $value = preg_replace('/\b(\d{2})(\d{2})(\d{2})\b/', '${1}${2}20${3}', (string) $value);

        return Str::slug(trim((string) $value));
    }

    private function score(string $normalizedFolder, string $normalizedProject): float
    {
        if ($normalizedFolder === $normalizedProject) {
            return 100.0;
        }

        similar_text($normalizedFolder, $normalizedProject, $textScore);

        $folderTokens = $this->tokens($normalizedFolder);
        $projectTokens = $this->tokens($normalizedProject);
        $intersection = array_intersect($folderTokens, $projectTokens);
        $union = array_unique([...$folderTokens, ...$projectTokens]);
        $tokenScore = count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;

        return max($textScore, $tokenScore);
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $normalized): array
    {
        return array_values(array_filter(explode('-', $normalized)));
    }
}
