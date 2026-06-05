<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\User;
use App\Support\MonthlyImageDigestPayload;
use App\Support\ProjectAccess;
use App\Support\TransactionalMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('images:send-monthly-digest {--since= : Date de debut YYYY-MM-DD, par defaut debut du mois precedent} {--dry-run : Liste les envois sans appeler Brevo}')]
#[Description('Envoie un recapitulatif mensuel Brevo des nouvelles images accessibles par utilisateur')]
class SendMonthlyImageDigest extends Command
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
        private readonly ProjectAccess $projectAccess,
        private readonly MonthlyImageDigestPayload $payload,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startsAt = $this->startsAt();
        $endsAt = now();
        $sent = 0;
        $skipped = 0;

        User::query()
            ->where('status', 'active')
            ->whereHas('clientMemberships', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($endsAt, &$sent, &$skipped, $startsAt): void {
                foreach ($users as $user) {
                    $images = Image::query()
                        ->with(['client:id,name', 'project:id,name'])
                        ->where('status', 'ready')
                        ->whereBetween('created_at', [$startsAt, $endsAt])
                        ->tap(fn ($query) => $this->projectAccess->applyImageVisibility($query, $user))
                        ->latest()
                        ->limit(100)
                        ->get();

                    if ($images->isEmpty()) {
                        $skipped++;

                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Dry-run: {$user->email} recevrait {$images->count()} image(s).");
                        $sent++;

                        continue;
                    }

                    if ($this->mailer->send('monthly_image_digest', [
                        [
                            'email' => $user->email,
                            'name' => $user->name,
                        ],
                    ], $this->payload->build($user, $images, $startsAt, $endsAt))) {
                        $sent++;
                    }
                }
            });

        $this->info("Digests mensuels envoyes: {$sent}; utilisateurs sans nouveautes: {$skipped}");

        return self::SUCCESS;
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
