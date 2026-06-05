<?php

namespace App\Support;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalMailer
{
    /**
     * @param  array<int, array{email: string, name?: string|null}>  $to
     * @param  array<string, mixed>  $params
     */
    public function send(string $templateKey, array $to, array $params = []): bool
    {
        $view = $this->localView($templateKey);

        if ($view === null) {
            Log::warning('Transactional email skipped because the local template is unknown.', [
                'template' => $templateKey,
                'recipients' => collect($to)->pluck('email')->all(),
            ]);

            return false;
        }

        foreach ($to as $recipient) {
            Mail::send($view, [
                'params' => $params,
                'recipient' => $recipient,
            ], function (Message $message) use ($params, $recipient, $templateKey): void {
                $message
                    ->to($recipient['email'], $recipient['name'] ?? null)
                    ->subject($this->localSubject($templateKey, $params));
            });
        }

        return true;
    }

    private function localView(string $templateKey): ?string
    {
        return match ($templateKey) {
            'user_invitation' => 'emails.user-invitation',
            'shared_album_invitation' => 'emails.shared-album-invitation',
            'monthly_image_digest' => 'emails.monthly-image-digest',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function localSubject(string $templateKey, array $params): string
    {
        return match ($templateKey) {
            'user_invitation' => 'Invitation à Stimergie Image Hub',
            'shared_album_invitation' => sprintf(
                'Album partagé : %s',
                $params['album_name'] ?? 'Stimergie Image Hub',
            ),
            'monthly_image_digest' => sprintf(
                '%d nouvelle(s) image(s) disponibles',
                $params['image_count'] ?? 0,
            ),
            default => 'Stimergie Image Hub',
        };
    }
}
