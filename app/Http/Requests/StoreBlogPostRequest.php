<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $clientId = $this->integer('client_id') ?: null;

        if ($clientId === null) {
            return $user->isSuperAdmin();
        }

        $client = Client::find($clientId);

        return $client instanceof Client && $user->can('update', $client);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $clientId = $this->integer('client_id') ?: null;
        $featuredImageRule = Rule::exists('images', 'id');

        if ($clientId !== null) {
            $featuredImageRule->where(fn ($query) => $query->where('client_id', $clientId));
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:100000'],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'content_type' => ['required', 'string', Rule::in(['resource', 'ensemble'])],
            'category' => ['nullable', 'string', Rule::in(['actualites', 'projets', 'conseils'])],
            'featured_image_id' => ['nullable', 'integer', $featuredImageRule],
            'remove_featured_image' => ['boolean'],
            'is_published' => ['boolean'],
        ];
    }
}
