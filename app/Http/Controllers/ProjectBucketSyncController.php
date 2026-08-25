<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectBucketImageSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectBucketSyncController extends Controller
{
    public function __construct(
        private readonly ProjectBucketImageSynchronizer $bucketImages,
    ) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $project->loadMissing('client');
        $this->authorizeProjectSync($request, $project);

        return response()->json($this->bucketImages->sync($project, $request->user()?->id));
    }

    private function authorizeProjectSync(Request $request, Project $project): void
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin()
            || ($project->client && $user?->hasClientRole($project->client, ['owner', 'manager'])), 403);
    }

}
