<?php

namespace App\Http\Requests;

use App\Models\Image;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignImagesProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::with('client')->find($this->integer('project_id'));
        $user = $this->user();
        $imageIds = $this->input('image_ids', []);

        if (! $project || ! $user || ! is_array($imageIds) || $imageIds === []) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->hasClientRole($project->client, ['owner', 'manager'])) {
            return false;
        }

        return Image::query()
            ->with('client')
            ->whereIn('id', $imageIds)
            ->get()
            ->every(fn (Image $image) => $user->hasClientRole($image->client, ['owner', 'manager']));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer', Rule::exists('images', 'id')],
        ];
    }
}
