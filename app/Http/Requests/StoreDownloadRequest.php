<?php

namespace App\Http\Requests;

use App\Models\Image;
use App\Support\ProjectAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $imageIds = $this->input('image_ids', []);

        if (! $user || ! is_array($imageIds) || $imageIds === []) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectAccess = app(ProjectAccess::class);
        $images = Image::query()
            ->with(['client', 'project.accessPeriods'])
            ->whereIn('id', $imageIds)
            ->get();

        if ($images->count() !== count(array_unique($imageIds))) {
            return false;
        }

        return $images->every(fn (Image $image) => $projectAccess->userCanViewImage($user, $image));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variant' => ['required', 'string', Rule::in(['web', 'hd'])],
            'image_ids' => ['required', 'array', 'min:1', 'max:100'],
            'image_ids.*' => ['integer', Rule::exists('images', 'id')],
        ];
    }
}
