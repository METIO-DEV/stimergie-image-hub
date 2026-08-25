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
     * @param  array<int, array{email: string, name?: string|null}>  $cc
     */
    public function send(string $templateKey, array $to, array $params = [], array $cc = []): bool
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
            ], function (Message $message) use ($cc, $params, $recipient, $templateKey): void {
                $message
                    ->to($recipient['email'], $recipient['name'] ?? null)
                    ->subject($this->localSubject($templateKey, $params));

                foreach ($cc as $copy) {
                    $message->cc($copy['email'], $copy['name'] ?? null);
                }

                if (isset($params['reply_to_email']) && is_string($params['reply_to_email'])) {
                    $message->replyTo($params['reply_to_email'], $params['reply_to_name'] ?? null);
                }
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
            'rights_extension_request' => 'emails.rights-extension-request',
            'contact_request' => 'emails.contact-request',
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
            'rights_extension_request' => sprintf(
                'Demande d extension de cession - %s',
                $params['image_title'] ?? 'image',
            ),
            'contact_request' => sprintf(
                'Message de contact - %s',
                $params['subject'] ?? 'Stimergie Image Hub',
            ),
            default => 'Stimergie Image Hub',
        };
    }
}
