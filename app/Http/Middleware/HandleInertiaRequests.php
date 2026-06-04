<?php

namespace App\Http\Middleware;

use App\Models\ProjectAccessPeriod;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $canManageClientContent = $user
            ? $user->isSuperAdmin()
                || ($user->isClientAdmin() && $user->hasAnyClientRole(['owner', 'manager']))
            : false;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'abilities' => [
                    'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
                    'canManageClientContent' => $canManageClientContent,
                    'canViewClientManagement' => $canManageClientContent,
                    'canManageUsers' => $user?->isSuperAdmin() ?? false,
                    'canViewUsers' => $user?->isSuperAdmin() ?? false,
                    'canViewAccessPeriods' => $user?->can('viewAny', ProjectAccessPeriod::class) ?? false,
                    'canManageAccessPeriods' => $user?->can('viewAny', ProjectAccessPeriod::class) ?? false,
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
