<?php

namespace App\Support;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class BrevoTemplateMailer
{
    /**
     * @param  array<int, array{email: string, name?: string|null}>  $to
     * @param  array<string, mixed>  $params
     */
    public function send(string $templateKey, array $to, array $params = []): bool
    {
        if (config('services.brevo.template_mailer') === 'laravel') {
            return $this->sendWithLaravel($templateKey, $to, $params);
        }

        $apiKey = (string) config('services.brevo.api_key');
        $templateId = $this->templateId($templateKey);

        if ($apiKey === '' || $templateId === null) {
            Log::warning('Brevo template email skipped because configuration is incomplete.', [
                'template' => $templateKey,
                'recipients' => collect($to)->pluck('email')->all(),
            ]);

            return false;
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'email' => config('services.brevo.sender_email'),
                'name' => config('services.brevo.sender_name'),
            ],
            'to' => $to,
            'templateId' => $templateId,
            'params' => $params,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Brevo failed to send template {$templateKey}.");
        }

        return true;
    }

    /**
     * @param  array<int, array{email: string, name?: string|null}>  $to
     * @param  array<string, mixed>  $params
     */
    private function sendWithLaravel(string $templateKey, array $to, array $params): bool
    {
        $view = $this->localView($templateKey);

        if ($view === null) {
            Log::warning('Local template email skipped because the template is unknown.', [
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
                    ->from(config('services.brevo.sender_email'), config('services.brevo.sender_name'))
                    ->to($recipient['email'], $recipient['name'] ?? null)
                    ->subject($this->localSubject($templateKey, $params));
            });
        }

        return true;
    }

    private function templateId(string $templateKey): ?int
    {
        $value = config("services.brevo.templates.{$templateKey}");

        return $value ? (int) $value : null;
    }

    private function localView(string $templateKey): ?string
    {
        return match ($templateKey) {
            'registration' => 'emails.registration',
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
            'registration' => 'Votre accès Stimergie Image Hub',
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
