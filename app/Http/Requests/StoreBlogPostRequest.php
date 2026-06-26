<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'external_links' => $this->normalizedExternalLinks(),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->input('content_type') === 'ensemble') {
            return $user->isSuperAdmin();
        }

        $clientId = $this->integer('client_id') ?: null;

        if ($clientId === null) {
            return false;
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
            'client_id' => [
                Rule::requiredIf($this->input('content_type') === 'resource'),
                'nullable',
                'integer',
                Rule::exists('clients', 'id'),
            ],
            'content_type' => ['required', 'string', Rule::in(['resource', 'ensemble'])],
            'category' => ['nullable', 'string', Rule::in(['actualites', 'projets', 'conseils'])],
            'featured_image_id' => ['nullable', 'integer', $featuredImageRule],
            'remove_featured_image' => ['boolean'],
            'external_links' => ['nullable', 'array', 'max:10'],
            'external_links.*.label' => ['nullable', 'string', 'max:120'],
            'external_links.*.url' => ['required', 'url', 'max:2048'],
            'is_published' => ['boolean'],
        ];
    }

    /**
     * @return array<int, array{label: string|null, url: string}>
     */
    private function normalizedExternalLinks(): array
    {
        return collect($this->input('external_links', []))
            ->filter(fn ($link) => is_array($link))
            ->map(fn (array $link) => [
                'label' => trim((string) ($link['label'] ?? '')) ?: null,
                'url' => trim((string) ($link['url'] ?? '')),
            ])
            ->filter(fn (array $link) => $link['label'] !== null || $link['url'] !== '')
            ->values()
            ->all();
    }
}
