<?php

namespace Tests\Feature;

use App\Support\TransactionalMailer;
use Tests\TestCase;

class BrevoMailDiagnosticsTest extends TestCase
{
    public function test_brevo_diagnostics_fail_when_brevo_smtp_credentials_are_missing(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'contact@stimergie.fr',
        ]);

        $this->artisan('mail:diagnose-brevo')
            ->expectsOutputToContain('Configuration email incomplète')
            ->assertFailed();
    }

    public function test_brevo_diagnostics_pass_for_local_mailpit_smtp(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mailpit',
            'mail.mailers.smtp.port' => 1025,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'hello@example.test',
        ]);

        $this->artisan('mail:diagnose-brevo')
            ->expectsOutputToContain('Configuration mail locale complète.')
            ->assertSuccessful();
    }

    public function test_brevo_diagnostics_pass_for_brevo_smtp(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'smtp-login',
            'mail.mailers.smtp.password' => 'smtp-key',
            'mail.from.address' => 'contact@stimergie.fr',
        ]);

        $this->artisan('mail:diagnose-brevo')
            ->expectsOutputToContain('Configuration SMTP Brevo complète.')
            ->assertSuccessful();
    }

    public function test_brevo_diagnostics_can_send_test_email_with_current_configuration(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'smtp-login',
            'mail.mailers.smtp.password' => 'smtp-key',
            'mail.from.address' => 'contact@stimergie.fr',
        ]);

        $this->mock(TransactionalMailer::class, function ($mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(fn (string $template, array $to, array $params) => $template === 'user_invitation'
                    && $to[0]['email'] === 'test@example.test'
                    && $params['user_email'] === 'test@example.test')
                ->andReturn(true);
        });

        $this->artisan('mail:diagnose-brevo', ['--send-to' => 'test@example.test'])
            ->expectsOutputToContain('Configuration SMTP Brevo complète.')
            ->expectsOutput('Email de test envoyé à test@example.test via la configuration courante.')
            ->assertSuccessful();
    }
}
