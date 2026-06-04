<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BrevoTemplateMailer
{
    /**
     * @param  array<int, array{email: string, name?: string|null}>  $to
     * @param  array<string, mixed>  $params
     */
    public function send(string $templateKey, array $to, array $params = []): bool
    {
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

    private function templateId(string $templateKey): ?int
    {
        $value = config("services.brevo.templates.{$templateKey}");

        return $value ? (int) $value : null;
    }
}
