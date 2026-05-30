<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Project::create([
            'client_id' => $data['client_id'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['client_id'], $data['name']),
            'type' => $data['type'] ?: null,
            'source_folder' => $data['source_folder'] ?: null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Projet cree.');
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

        return back()->with('success', 'Projet mis a jour.');
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
}
