<?php

namespace App\Http\Middleware;

use Inertia\Inertia;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...Inertia::getShared(),
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user?->append(['name', 'two_factor_enabled', 'has_password']),
            ],
            'sidebarOpen' => !$request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'newToken' => $request->session()->get('newToken'),
            ],
            'can' => [
                'administrate' => $user?->can('administrate') ?? false,
            ],
            'features' => [
                'passwordAuthentication' => Features::enabled(Features::updatePasswords()),
                'laravelPassportSso' => filled(config('services.laravelpassport.client_id')) ? [
                    'label' => config('services.laravelpassport.label'),
                    'logo' => config('services.laravelpassport.logo'),
                ] : false,
            ],
            'impersonating' => $request->session()->has('impersonator_id'),
        ];
    }
}
