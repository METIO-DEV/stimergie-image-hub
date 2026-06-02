<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::with('client')->find($this->integer('project_id'));

        return $project instanceof Project
            && ($this->user()?->isSuperAdmin()
                || ($this->user()?->hasClientRole($project->client, ['owner', 'manager']) ?? false));
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
            'file' => ['required', 'image', 'max:102400'],
        ];
    }
}
