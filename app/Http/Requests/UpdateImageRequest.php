<?php

namespace App\Http\Requests;

use App\Models\Image;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $image = $this->route('image');
        $project = Project::with('client')->find($this->integer('project_id'));
        $user = $this->user();

        if (! $image instanceof Image || ! $project instanceof Project || ! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasClientRole($image->client, ['owner', 'manager'])
            && $user->hasClientRole($project->client, ['owner', 'manager']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'orientation' => ['nullable', 'string', Rule::in(['landscape', 'portrait', 'square'])],
            'status' => ['required', 'string', Rule::in(['ready', 'pending_upload', 'archived'])],
            'tags' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'image', 'max:20480'],
        ];
    }
}
