<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectAccessPeriodRequest;
use App\Http\Requests\UpdateProjectAccessPeriodRequest;
use App\Models\ProjectAccessPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectAccessPeriodController extends Controller
{
    public function store(StoreProjectAccessPeriodRequest $request): RedirectResponse
    {
        $data = $request->validated();

        ProjectAccessPeriod::create([
            ...$data,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Période d'accès créée.");
    }

    public function update(UpdateProjectAccessPeriodRequest $request, ProjectAccessPeriod $accessPeriod): RedirectResponse
    {
        $data = $request->validated();

        $accessPeriod->update([
            ...$data,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
        ]);

        return back()->with('success', "Période d'accès mise à jour.");
    }

    public function destroy(Request $request, ProjectAccessPeriod $accessPeriod): RedirectResponse
    {
        Gate::authorize('delete', $accessPeriod);

        $accessPeriod->delete();

        return back()->with('success', "Période d'accès supprimée.");
    }
}
