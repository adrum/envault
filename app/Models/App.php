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

    public function notificationsEnabled(): bool
    {
        return $this->slack_notification_channel && $this->slack_notification_webhook_url;
    }

    public function routeNotificationForSlack(Notification $notification): ?string
    {
        return $this->slack_notification_webhook_url;
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'app_collaborators')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function log(): MorphMany
    {
        return $this->morphMany(LogEntry::class, 'loggable');
    }

    /**
     * @return HasMany<AppSetupToken, $this>
     */
    public function setup_tokens(): HasMany
    {
        return $this->hasMany(AppSetupToken::class);
    }

    /**
     * @return HasMany<Variable, $this>
     */
    public function variables(): HasMany
    {
        return $this->hasMany(Variable::class)->orderBy('sort_order');
    }
}
