<?php

namespace App\Console\Commands;

use App\Support\TransactionalMailer;
use Illuminate\Console\Command;

class DiagnoseBrevoMail extends Command
{
    protected $signature = 'mail:diagnose-brevo {--send-to= : Adresse email qui recevra un email de test via la configuration courante}';

    protected $description = 'Vérifie la configuration mail Laravel/Brevo sans afficher les secrets';

    public function __construct(private readonly TransactionalMailer $mailer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $mailHost = (string) config("mail.mailers.{$mailer}.host");
        $mailPort = (string) config("mail.mailers.{$mailer}.port");
        $mailScheme = config("mail.mailers.{$mailer}.scheme");
        $usernameConfigured = filled(config("mail.mailers.{$mailer}.username"));
        $passwordConfigured = filled(config("mail.mailers.{$mailer}.password"));
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        $usesSmtp = $mailer === 'smtp';
        $usesBrevoSmtp = $usesSmtp && $mailHost === 'smtp-relay.brevo.com';

        $this->line('Configuration email');
        $this->table(['Clé', 'Valeur'], [
            ['APP_ENV', config('app.env')],
            ['MAIL_MAILER', $mailer],
            ['MAIL_HOST', $mailHost !== '' ? $mailHost : 'absent'],
            ['MAIL_PORT', $mailPort !== '' ? $mailPort : 'absent'],
            ['MAIL_SCHEME', filled($mailScheme) ? (string) $mailScheme : 'absent'],
            ['MAIL_USERNAME', $usernameConfigured ? 'configuré' : 'absent'],
            ['MAIL_PASSWORD', $passwordConfigured ? 'configuré' : 'absent'],
            ['MAIL_FROM_ADDRESS', $fromAddress !== '' ? $fromAddress : 'absent'],
            ['MAIL_FROM_NAME', $fromName !== '' ? $fromName : 'absent'],
        ]);

        $views = [
            'emails.user-invitation' => resource_path('views/emails/user-invitation.blade.php'),
            'emails.shared-album-invitation' => resource_path('views/emails/shared-album-invitation.blade.php'),
            'emails.monthly-image-digest' => resource_path('views/emails/monthly-image-digest.blade.php'),
        ];

        $this->line('Templates locaux');
        $this->table(['Vue', 'Statut'], collect($views)
            ->map(fn (string $path, string $view) => [$view, is_file($path) ? 'présente' : 'absente'])
            ->values()
            ->all());

        $missing = collect($views)
            ->filter(fn (string $path) => ! is_file($path))
            ->keys()
            ->all();

        if (! $usesSmtp) {
            $this->warn('MAIL_MAILER n’est pas smtp. Ce diagnostic ne peut pas valider une connexion SMTP Brevo.');
        }

        if ($usesBrevoSmtp && (! $usernameConfigured || ! $passwordConfigured)) {
            $missing[] = 'MAIL_USERNAME/MAIL_PASSWORD Brevo';
        }

        if ($fromAddress === '') {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }

        if ($missing !== []) {
            $this->error('Configuration email incomplète : '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->info($usesBrevoSmtp
            ? 'Configuration SMTP Brevo complète.'
            : 'Configuration mail locale complète.');

        $sendTo = (string) $this->option('send-to');

        if ($sendTo !== '') {
            if (! filter_var($sendTo, FILTER_VALIDATE_EMAIL)) {
                $this->error("Adresse email invalide : {$sendTo}");

                return self::FAILURE;
            }

            $this->mailer->send('user_invitation', [
                [
                    'email' => $sendTo,
                    'name' => 'Test Stimergie',
                ],
            ], [
                'user_name' => 'Test Stimergie',
                'user_email' => $sendTo,
                'login_url' => route('login'),
                'reset_password_url' => route('password.reset', [
                    'token' => 'diagnostic-token',
                    'email' => $sendTo,
                ]),
            ]);

            $this->info("Email de test envoyé à {$sendTo} via la configuration courante.");
        }

        return self::SUCCESS;
    }
}
