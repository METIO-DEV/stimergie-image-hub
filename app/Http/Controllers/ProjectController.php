<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Import;
use App\Models\Project;
use App\Support\StoredImageObjectCleaner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        private readonly StoredImageObjectCleaner $objectCleaner,
    ) {}

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $client = Client::findOrFail($data['client_id']);
        $slug = $this->uniqueSlug($client->id, $data['name']);

        Project::create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'] ?: null,
            'source_folder' => $this->normalizedSourceFolder($data['source_folder'] ?? null)
                ?: $this->generatedSourceFolder($client, $slug),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Projet créé.');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        $project->update([
            'client_id' => $data['client_id'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['client_id'], $data['name'], $project),
            'type' => $data['type'] ?: null,
            'source_folder' => $data['source_folder'] ?: null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Projet mis à jour.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $project->client), 403);

        $images = $project->images()->get();

        DB::transaction(function () use ($project): void {
            Import::query()
                ->where('project_id', $project->id)
                ->delete();

            $project->delete();
        });

        $this->objectCleaner->deleteImageObjects($images);

        return back()->with('success', 'Projet supprimé.');
    }

    private function uniqueSlug(int $clientId, string $name, ?Project $project = null): string
    {
        $base = Str::slug($name) ?: 'projet';
        $slug = $base;
        $suffix = 2;

        while (Project::query()
            ->where('client_id', $clientId)
            ->where('slug', $slug)
            ->when($project, fn ($query) => $query->whereKeyNot($project->id))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function generatedSourceFolder(Client $client, string $projectSlug): string
    {
        $clientSegment = Str::slug($client->slug ?: $client->name) ?: "entreprise-{$client->id}";

        return "{$clientSegment}/{$projectSlug}";
    }

    private function normalizedSourceFolder(?string $sourceFolder): ?string
    {
        $sourceFolder = trim((string) $sourceFolder);

        return $sourceFolder !== '' ? $sourceFolder : null;
    }
}
