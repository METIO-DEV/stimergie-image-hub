<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Image;
use App\Models\User;
use App\Support\BrevoTemplateMailer;
use App\Support\MonthlyImageDigestPayload;
use App\Support\ProjectAccess;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('images:test-monthly-digest
    {client : ID, slug ou nom de l entreprise dont les utilisateurs recevront le test}
    {--since= : Date de debut YYYY-MM-DD, par defaut debut du mois precedent}
    {--latest : Utilise les dernieres images disponibles de l entreprise, sans filtre de date}
    {--sample : Force les donnees de demonstration}
    {--use-current-mailer : Ne force pas le transport SMTP Mailpit}')]
#[Description('Envoie un recapitulatif mensuel de test aux utilisateurs actifs rattaches a une entreprise')]
class TestMonthlyImageDigest extends Command
{
    public function __construct(
        private readonly BrevoTemplateMailer $brevo,
        private readonly MonthlyImageDigestPayload $payload,
        private readonly ProjectAccess $projectAccess,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $client = $this->client();

        if (! $client) {
            $this->error("Entreprise introuvable: {$this->argument('client')}");

            return self::FAILURE;
        }

        $this->configureLocalMailer();

        $startsAt = $this->startsAt();
        $endsAt = now();
        $sent = 0;
        $skipped = 0;

        $client->memberships()
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->with('user')
            ->orderBy('user_id')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(function (User $user) use ($client, $endsAt, &$sent, &$skipped, $startsAt): void {
                $params = $this->option('sample')
                    ? $this->sampleParams($client, $user, $startsAt, $endsAt)
                    : $this->realParams($client, $user, $startsAt, $endsAt, $this->option('latest'));

                if ($params === null) {
                    $this->line("Aucune image recente pour {$user->email}; aucun mail envoye.");
                    $skipped++;

                    return;
                }

                if ($this->brevo->send('monthly_image_digest', [
                    [
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                ], $params)) {
                    $this->line("Mail mensuel de test envoye a {$user->email}.");
                    $sent++;
                }
            });

        $this->info("Mails mensuels de test envoyes pour {$client->name}: {$sent}; utilisateurs sans nouveautes: {$skipped}");

        return self::SUCCESS;
    }

    private function configureLocalMailer(): void
    {
        config(['services.brevo.template_mailer' => 'laravel']);

        if ($this->option('use-current-mailer')) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => file_exists('/.dockerenv') ? 'mailpit' : '127.0.0.1',
            'mail.mailers.smtp.port' => 1025,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.mailers.smtp.scheme' => null,
        ]);
    }

    private function client(): ?Client
    {
        $client = (string) $this->argument('client');

        return Client::query()
            ->where('slug', $client)
            ->orWhere('name', $client)
            ->when(is_numeric($client), fn ($query) => $query->orWhere('id', (int) $client))
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function realParams(Client $client, User $user, Carbon $startsAt, Carbon $endsAt, bool $latest = false): ?array
    {
        $images = Image::query()
            ->with(['client:id,name', 'project:id,name'])
            ->where('client_id', $client->id)
            ->where('status', 'ready')
            ->when(! $latest, fn ($query) => $query->whereBetween('created_at', [$startsAt, $endsAt]))
            ->tap(fn ($query) => $this->projectAccess->applyImageVisibility($query, $user))
            ->latest()
            ->limit(100)
            ->get();

        if ($images->isEmpty()) {
            return null;
        }

        return $this->payload->build($user, $images, $startsAt, $endsAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleParams(Client $client, User $user, Carbon $startsAt, Carbon $endsAt): array
    {
        return [
            'user_name' => $user->name,
            'period_start' => $startsAt->toDateString(),
            'period_end' => $endsAt->toDateString(),
            'image_count' => 6,
            'gallery_url' => route('gallery.index'),
            'projects' => [
                [
                    'project_name' => 'Renouvellement urbain',
                    'client_name' => $client->name,
                    'image_count' => 3,
                    'preview_images' => $this->sampleImages('Urbain', '274854'),
                ],
                [
                    'project_name' => 'Suivi chantier',
                    'client_name' => $client->name,
                    'image_count' => 3,
                    'preview_images' => $this->sampleImages('Chantier', '111111'),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, thumbnail_url: string, url: string}>
     */
    private function sampleImages(string $label, string $color): array
    {
        return collect(range(1, 3))
            ->map(fn (int $index) => [
                'title' => "{$label} {$index}",
                'thumbnail_url' => "https://placehold.co/360x240/{$color}/F2F0F0/png?text={$label}+{$index}",
                'url' => route('gallery.index'),
            ])
            ->all();
    }

    private function startsAt(): Carbon
    {
        $since = $this->option('since');

        if ($since) {
            return Carbon::parse($since)->startOfDay();
        }

        return now()->subMonthNoOverflow()->startOfMonth();
    }
}
