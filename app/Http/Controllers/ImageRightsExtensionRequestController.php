<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Image;
use App\Models\ImageRightsExtensionRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImageRightsExtensionRequestController extends Controller
{
    public function update(Request $request, ImageRightsExtensionRequest $rightsExtensionRequest): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->canManageRequest($user, $rightsExtensionRequest), 403);

        $data = $this->validatedData($request);

        $previousStatus = $rightsExtensionRequest->status;
        $extendedRightsEndsAt = $this->applyStatusUpdate($rightsExtensionRequest, $user, $data);

        $this->writeAuditLog($request, $rightsExtensionRequest, $previousStatus, $data['status'], $extendedRightsEndsAt);

        return back()->with('success', $extendedRightsEndsAt
            ? 'Cession prolongée et demande acceptée.'
            : 'Statut de demande mis à jour.');
    }

    public function updateLegacy(Request $request, Image $image): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->canManageImage($user, $image), 403);
        abort_unless($image->rights_extension_requested_at !== null, 422);

        $data = $this->validatedData($request);
        $this->assertAcceptedDateExtendsCurrentRights($image->rights_ends_at, $data);

        $rightsExtensionRequest = ImageRightsExtensionRequest::create([
            'image_id' => $image->id,
            'client_id' => $image->client_id,
            'project_id' => $image->project_id,
            'requested_by' => $image->rights_extension_requested_by,
            'status' => ImageRightsExtensionRequest::STATUS_REQUESTED,
            'rights_ends_at' => $image->rights_ends_at,
            'created_at' => $image->rights_extension_requested_at,
            'updated_at' => $image->rights_extension_requested_at,
            'metadata' => [
                'image_title' => $image->title,
                'client_name' => $image->client?->name,
                'project_name' => $image->project?->name,
                'legacy_image_request' => true,
            ],
        ]);

        $extendedRightsEndsAt = $this->applyStatusUpdate($rightsExtensionRequest, $user, $data);
        $this->writeAuditLog($request, $rightsExtensionRequest, ImageRightsExtensionRequest::STATUS_REQUESTED, $data['status'], $extendedRightsEndsAt);

        return back()->with('success', $extendedRightsEndsAt
            ? 'Cession prolongée et demande acceptée.'
            : 'Statut de demande mis à jour.');
    }

    private function applyStatusUpdate(ImageRightsExtensionRequest $rightsExtensionRequest, User $user, array $data): ?CarbonImmutable
    {
        $nextStatus = $data['status'];
        $extendedRightsEndsAt = $this->extendedRightsEndsAt($rightsExtensionRequest, $data);

        DB::transaction(function () use ($rightsExtensionRequest, $nextStatus, $user, $extendedRightsEndsAt): void {
            $metadata = $rightsExtensionRequest->metadata ?: [];

            if ($extendedRightsEndsAt) {
                $metadata['extended_rights_ends_at'] = $extendedRightsEndsAt->toDateString();
            }

            $rightsExtensionRequest->forceFill([
                'status' => $nextStatus,
                'resolved_by' => in_array($nextStatus, [
                    ImageRightsExtensionRequest::STATUS_ACCEPTED,
                    ImageRightsExtensionRequest::STATUS_REFUSED,
                ], true) ? $user->id : null,
                'resolved_at' => in_array($nextStatus, [
                    ImageRightsExtensionRequest::STATUS_ACCEPTED,
                    ImageRightsExtensionRequest::STATUS_REFUSED,
                ], true) ? now() : null,
                'metadata' => $metadata,
            ])->save();

            if ($extendedRightsEndsAt && $rightsExtensionRequest->image) {
                $rightsExtensionRequest->image->forceFill([
                    'rights_ends_at' => $extendedRightsEndsAt->toDateString(),
                    'rights_extension_requested_at' => null,
                    'rights_extension_requested_by' => null,
                ])->save();
            }
        });

        return $extendedRightsEndsAt;
    }

    /**
     * @return array{status: string, extended_rights_ends_at?: string|null}
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'status' => ['required', 'string', Rule::in(ImageRightsExtensionRequest::STATUSES)],
            'extended_rights_ends_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $request->string('status')->toString() === ImageRightsExtensionRequest::STATUS_ACCEPTED),
            ],
        ]);
    }

    private function extendedRightsEndsAt(ImageRightsExtensionRequest $rightsExtensionRequest, array $data): ?CarbonImmutable
    {
        if ($data['status'] !== ImageRightsExtensionRequest::STATUS_ACCEPTED) {
            return null;
        }

        $rightsExtensionRequest->loadMissing('image');
        $this->assertAcceptedDateExtendsCurrentRights(
            $rightsExtensionRequest->image?->rights_ends_at ?: $rightsExtensionRequest->rights_ends_at,
            $data,
        );

        return CarbonImmutable::parse($data['extended_rights_ends_at'])->startOfDay();
    }

    private function assertAcceptedDateExtendsCurrentRights(mixed $currentRightsEndsAt, array $data): void
    {
        if ($data['status'] !== ImageRightsExtensionRequest::STATUS_ACCEPTED) {
            return;
        }

        $extendedRightsEndsAt = CarbonImmutable::parse($data['extended_rights_ends_at'])->startOfDay();

        if ($currentRightsEndsAt && $extendedRightsEndsAt->lte($currentRightsEndsAt)) {
            throw ValidationException::withMessages([
                'extended_rights_ends_at' => 'La nouvelle fin de cession doit être postérieure à la fin actuelle.',
            ]);
        }
    }

    private function writeAuditLog(
        Request $request,
        ImageRightsExtensionRequest $rightsExtensionRequest,
        string $previousStatus,
        string $nextStatus,
        ?CarbonImmutable $extendedRightsEndsAt,
    ): void {
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'client_id' => $rightsExtensionRequest->client_id,
            'action' => 'image.rights_extension_status_updated',
            'subject_type' => ImageRightsExtensionRequest::class,
            'subject_id' => $rightsExtensionRequest->id,
            'properties' => [
                'image_id' => $rightsExtensionRequest->image_id,
                'previous_status' => $previousStatus,
                'status' => $nextStatus,
                'extended_rights_ends_at' => $extendedRightsEndsAt?->toDateString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function canManageRequest(User $user, ImageRightsExtensionRequest $request): bool
    {
        return $user->isSuperAdmin()
            || $user->hasClientRole($request->client, ['owner', 'manager']);
    }

    private function canManageImage(User $user, Image $image): bool
    {
        return $user->isSuperAdmin()
            || $user->hasClientRole($image->client, ['owner', 'manager']);
    }
}
