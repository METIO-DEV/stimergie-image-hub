<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSharedAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status === 'active'
            && ($user->isSuperAdmin() || $user->hasAnyClientRole(['owner', 'manager']));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'recipients' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image_ids' => ['required', 'array', 'min:1', 'max:200'],
            'image_ids.*' => ['integer', Rule::exists('images', 'id')],
        ];
    }
}
