<?php

namespace App\Providers;

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSecureUrls();
        $this->configureMorphMap();
        $this->configureGates();
        $this->configureRateLimiting();

        Date::setToStringFormat(DATE_ATOM);

        Model::preventLazyLoading(!$this->app->environment('production'));

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('laravelpassport', \SocialiteProviders\LaravelPassport\Provider::class);
        });
    }

    protected function configureSecureUrls()
    {
        // Determine if HTTPS should be enforced
        $enforceHttps = ($this->app->environment(['production', 'staging', 'development']) || strpos(config('app.url'), 'https') === 0)
            && !$this->app->runningUnitTests();

        // Force HTTPS for all generated URLs
        if ($enforceHttps) {
            URL::forceHttps();
            URL::useOrigin(config('app.url'));

            // Ensure proper server variable is set
            $this->app['request']->server->set('HTTPS', 'on');
            // Set up global middleware for security headers
            config('secure-headers.hsts.enable', true);
            config('secure-headers.csp.upgrade-insecure-requests', true);
        }
    }

    /**
     * Configure the morph map for polymorphic relationships.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'app' => App::class,
            'user' => User::class,
            'variable' => Variable::class,
        ]);
    }

    /**
     * Configure authorization gates.
     */
    protected function configureGates(): void
    {
        Gate::define('administrate', function (User $user): bool {
            return $user->isAdminOrOwner();
        });
    }

    /**
     * Configure API rate limiting.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
