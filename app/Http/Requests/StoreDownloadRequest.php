<?php

namespace App\Http\Requests;

use App\Models\Image;
use App\Support\ImageExportPresets;
use App\Support\ProjectAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDownloadRequest extends FormRequest
{
    private const MAX_IMAGE_COUNT = 100;

    private const MAX_HD_IMAGE_COUNT = 50;

    private const MAX_ESTIMATED_ARCHIVE_BYTES = 1_500_000_000;

    public function authorize(): bool
    {
        $user = $this->user();
        $imageIds = $this->input('image_ids', []);

        if (! $user || $user->status !== 'active' || ! is_array($imageIds) || $imageIds === []) {
            return false;
        }

        $uniqueImageIds = collect($imageIds)
            ->map(fn ($imageId) => (int) $imageId)
            ->filter()
            ->unique()
            ->values();

        if ($uniqueImageIds->isEmpty()) {
            return false;
        }

        $projectAccess = app(ProjectAccess::class);
        $images = Image::query()
            ->with(['client', 'project.accessPeriods'])
            ->whereIn('id', $uniqueImageIds)
            ->get();

        if ($images->count() !== $uniqueImageIds->count()) {
            return false;
        }

        if ($images->contains(fn (Image $image) => $image->rightsAreExpired())) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $images->every(fn (Image $image) => $projectAccess->userCanViewImage($user, $image));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variant' => ['required', 'string', Rule::in(['web', 'hd', 'crop'])],
            'image_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IMAGE_COUNT],
            'image_ids.*' => ['integer', 'distinct', Rule::exists('images', 'id')],
            'crop_preset' => ['nullable', 'required_if:variant,crop', 'string', Rule::in(array_keys(ImageExportPresets::all()))],
            'crop_source' => ['nullable', 'required_if:variant,crop', 'string', Rule::in(['web', 'hd'])],
            'crops' => ['nullable', 'required_if:variant,crop', 'array'],
            'crops.*.image_id' => ['required_with:crops', 'integer', 'distinct', Rule::exists('images', 'id')],
            'crops.*.focus_x' => ['required_with:crops', 'numeric', 'min:0', 'max:1'],
            'crops.*.focus_y' => ['required_with:crops', 'numeric', 'min:0', 'max:1'],
            'crops.*.zoom' => ['required_with:crops', 'numeric', 'min:1', 'max:4'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $imageIds = $this->input('image_ids', []);

                if ($validator->errors()->isNotEmpty() || ! is_array($imageIds)) {
                    return;
                }

                if ($this->input('variant') === 'hd' && count($imageIds) > self::MAX_HD_IMAGE_COUNT) {
                    $validator->errors()->add(
                        'image_ids',
                        'Les téléchargements HD sont limités à '.self::MAX_HD_IMAGE_COUNT.' images par archive.',
                    );

                    return;
                }

                if ($this->input('variant') === 'crop') {
                    $requestedIds = collect($imageIds)
                        ->map(fn ($imageId) => (int) $imageId)
                        ->sort()
                        ->values()
                        ->all();
                    $cropIds = collect($this->input('crops', []))
                        ->pluck('image_id')
                        ->map(fn ($imageId) => (int) $imageId)
                        ->sort()
                        ->values()
                        ->all();

                    if ($requestedIds !== $cropIds) {
                        $validator->errors()->add(
                            'crops',
                            'Chaque image exportée doit avoir un cadrage.',
                        );

                        return;
                    }
                }

                $estimatedBytes = Image::query()
                    ->whereIn('id', $imageIds)
                    ->sum('size_bytes');

                if ($estimatedBytes > self::MAX_ESTIMATED_ARCHIVE_BYTES) {
                    $validator->errors()->add(
                        'image_ids',
                        'La taille estimée de cette archive dépasse 1,5 Go. Réduisez la sélection.',
                    );
                }
            },
        ];
    }
}
