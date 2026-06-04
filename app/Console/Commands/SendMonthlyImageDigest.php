<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\User;
use App\Support\BrevoTemplateMailer;
use App\Support\ImageUrlResolver;
use App\Support\ProjectAccess;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('images:send-monthly-digest {--since= : Date de debut YYYY-MM-DD, par defaut debut du mois precedent} {--dry-run : Liste les envois sans appeler Brevo}')]
#[Description('Envoie un recapitulatif mensuel Brevo des nouvelles images accessibles par utilisateur')]
class SendMonthlyImageDigest extends Command
{
    public function __construct(
        private readonly BrevoTemplateMailer $brevo,
        private readonly ProjectAccess $projectAccess,
        private readonly ImageUrlResolver $imageUrls,
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

                    if ($this->brevo->send('monthly_image_digest', [
                        [
                            'email' => $user->email,
                            'name' => $user->name,
                        ],
                    ], [
                        'user_name' => $user->name,
                        'period_start' => $startsAt->toDateString(),
                        'period_end' => $endsAt->toDateString(),
                        'image_count' => $images->count(),
                        'gallery_url' => route('gallery.index'),
                        'projects' => $images
                            ->groupBy('project_id')
                            ->map(fn ($projectImages) => [
                                'project_name' => $projectImages->first()->project?->name,
                                'client_name' => $projectImages->first()->client?->name,
                                'image_count' => $projectImages->count(),
                            ])
                            ->values()
                            ->all(),
                        'images' => $images->take(20)->map(fn (Image $image) => [
                            'title' => $image->title,
                            'project_name' => $image->project?->name,
                            'client_name' => $image->client?->name,
                            'url' => $this->imageUrls->displayUrl($image),
                        ])->values()->all(),
                    ])) {
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
