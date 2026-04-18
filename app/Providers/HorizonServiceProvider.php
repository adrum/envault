<?php

namespace App\Providers;

use App\Models\User;
use Laravel\Horizon\Horizon;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        $allowedAdmin = function ($user = null) {
            // Allow access if the user's IP is in the VPN IP list
            if (in_array(request()->getClientIp(), config('settings.vpn_ip'))) {
                return true;
            }

            /** @var User|null $user */
            if ($user?->isAdminOrOwner()) {
                return true;
            }

            if ($user && in_array($user->email, [
                //
            ])) {
                return true;
            }

            return false;
        };

        Gate::define('viewHorizon', $allowedAdmin);

        Gate::define('viewPulse', $allowedAdmin);
    }
}
