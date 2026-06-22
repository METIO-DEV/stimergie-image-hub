<?php

namespace App\Support;

use App\Models\ImageRightsExtensionRequest;
use App\Models\User;

class ImageRightsExtensionRequestMailer
{
    public function __construct(private readonly TransactionalMailer $mailer) {}

    public function send(ImageRightsExtensionRequest $request): bool
    {
        $request->loadMissing(['image', 'client', 'project', 'requester']);
        $recipients = $this->recipients($request);

        if ($recipients === []) {
            return false;
        }

        return $this->mailer->send('rights_extension_request', $recipients, [
            'request_id' => $request->id,
            'status' => $request->status,
            'status_label' => $request->statusLabel(),
            'image_id' => $request->image_id,
            'image_title' => $request->image?->title,
            'project_name' => $request->project?->name,
            'client_name' => $request->client?->name,
            'rights_ends_at' => $request->rights_ends_at?->toDateString(),
            'requested_by_name' => $request->requester?->name,
            'requested_by_email' => $request->requester?->email,
            'requested_at' => $request->created_at?->toIso8601String(),
            'admin_url' => route('images.index'),
        ]);
    }

    /**
     * @return array<int, array{email: string, name?: string|null}>
     */
    private function recipients(ImageRightsExtensionRequest $request): array
    {
        $superAdmins = User::query()
            ->where('status', 'active')
            ->where('platform_role', 'super_admin')
            ->get(['id', 'name', 'email']);

        $clientAdmins = User::query()
            ->where('status', 'active')
            ->whereHas('clientMemberships', function ($query) use ($request): void {
                $query
                    ->where('client_id', $request->client_id)
                    ->where('status', 'active')
                    ->whereIn('role', ['owner', 'manager']);
            })
            ->get(['id', 'name', 'email']);

        return $superAdmins
            ->merge($clientAdmins)
            ->unique('email')
            ->values()
            ->map(fn (User $user): array => [
                'email' => $user->email,
                'name' => $user->name,
            ])
            ->all();
    }
}
