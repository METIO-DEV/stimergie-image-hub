<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ImageRightsExtensionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImageRightsExtensionRequestController extends Controller
{
    public function update(Request $request, ImageRightsExtensionRequest $rightsExtensionRequest): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->canManageRequest($user, $rightsExtensionRequest), 403);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(ImageRightsExtensionRequest::STATUSES)],
        ]);

        $previousStatus = $rightsExtensionRequest->status;
        $nextStatus = $data['status'];

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
        ])->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'client_id' => $rightsExtensionRequest->client_id,
            'action' => 'image.rights_extension_status_updated',
            'subject_type' => ImageRightsExtensionRequest::class,
            'subject_id' => $rightsExtensionRequest->id,
            'properties' => [
                'image_id' => $rightsExtensionRequest->image_id,
                'previous_status' => $previousStatus,
                'status' => $nextStatus,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Statut de demande mis à jour.');
    }

    private function canManageRequest(User $user, ImageRightsExtensionRequest $request): bool
    {
        return $user->isSuperAdmin()
            || $user->hasClientRole($request->client, ['owner', 'manager']);
    }
}
