<?php

namespace App\Providers;

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        $this->configureDefaults();
        $this->configureSecureUrls();
        $this->configureMorphMap();
        $this->configureGates();
        $this->configureRateLimiting();

        Date::setToStringFormat(DATE_ATOM);

        Model::preventLazyLoading(!$this->app->environment('production'));

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
