<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true
            && $this->user()?->status === 'active';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', Rule::in(['admin', 'admin_client', 'user'])],
            'status' => ['required', 'string', Rule::in(['active', 'paused'])],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'client_ids' => ['array'],
            'client_ids.*' => ['integer', Rule::exists('clients', 'id')],
        ];
    }
}
