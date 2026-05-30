<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $targetClient = Client::find($this->integer('client_id'));

        return $project instanceof Project
            && $targetClient instanceof Client
            && ($this->user()?->can('update', $project->client) ?? false)
            && ($this->user()?->can('update', $targetClient) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'type' => ['nullable', 'string', 'max:255'],
            'source_folder' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['active', 'paused', 'archived'])],
        ];
    }
}
