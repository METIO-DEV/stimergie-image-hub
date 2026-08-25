<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Image;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImageClientShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        $image = $this->route('image');
        $targetClient = Client::find($this->integer('client_id'));
        $user = $this->user();

        if (! $image instanceof Image || ! $targetClient instanceof Client || ! $user) {
            return false;
        }

        if ($image->client_id === $targetClient->id) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasClientRole($image->client, ['owner', 'manager'])
            && $user->hasClientRole($targetClient, ['owner', 'manager']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
