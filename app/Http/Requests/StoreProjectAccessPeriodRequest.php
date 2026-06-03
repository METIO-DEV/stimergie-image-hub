<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectAccessPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = Client::find($this->integer('client_id'));
        $project = Project::with('client')->find($this->integer('project_id'));

        return $client instanceof Client
            && $project instanceof Project
            && ($this->user()?->can('create', [ProjectAccessPeriod::class, $client, $project]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
