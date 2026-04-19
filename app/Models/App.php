<?php

namespace App\Models;

use App\Observers\AppObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy([AppObserver::class])]
class App extends Model
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (App $app) {
            if ($app->isForceDeleting()) {
                return;
            }

            $app->environments()->each(function ($environment) {
                $environment->variables()->delete();
                $environment->delete();
            });
        });

        static::restoring(function (App $app) {
            $app->environments()->withTrashed()->each(function ($environment) {
                $environment->restore();
                $environment->variables()->withTrashed()->restore();
            });
        });
    }

    public function notificationsEnabled(): bool
    {
        return $this->slack_notification_channel && $this->slack_notification_webhook_url;
    }

    public function routeNotificationForSlack(Notification $notification): ?string
    {
        return $this->slack_notification_webhook_url;
    }

    /** @return HasMany<Environment, $this> */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class)
            ->join('environment_types', 'environments.environment_type_id', '=', 'environment_types.id')
            ->orderBy('environment_types.sort_order')
            ->select('environments.*');
    }

    /** @return BelongsToMany<User, $this> */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'app_collaborators')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /** @return MorphMany<LogEntry, $this> */
    public function log(): MorphMany
    {
        return $this->morphMany(LogEntry::class, 'loggable');
    }

    /** @return HasMany<AppSetupToken, $this> */
    public function setup_tokens(): HasMany
    {
        return $this->hasMany(AppSetupToken::class);
    }

    /** @return HasMany<Variable, $this> */
    public function variables(): HasMany
    {
        return $this->hasMany(Variable::class)->orderBy('sort_order');
    }
}
