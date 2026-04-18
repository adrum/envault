<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Observers\UserObserver;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
    ];

    /** @return Attribute<non-falsy-string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => $this->first_name . ' ' . $this->last_name);
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => $this->first_name . ' ' . $this->last_name);
    }

    /** @return Attribute<bool, never> */
    protected function twoFactorEnabled(): Attribute
    {
        return Attribute::get(fn (): bool => !is_null($this->two_factor_confirmed_at));
    }

    /** @return Attribute<bool, never> */
    protected function hasPassword(): Attribute
    {
        return Attribute::get(fn (): bool => !is_null($this->password));
    }

    public function isAdminOrOwner(): bool
    {
        return in_array($this->role, [UserRole::ADMIN, UserRole::OWNER]);
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    public function isAppAdmin(App $app): bool
    {
        /** @var App|null $related */
        $related = $this->app_collaborations()->wherePivot('app_id', $app->id)->first();

        return $related !== null && $related->getAttribute('pivot')->getAttribute('role') === 'admin';
    }

    public function isAppCollaborator(App $app): bool
    {
        return $this->app_collaborations()->wherePivot('app_id', $app->id)->exists();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(LogEntry::class);
    }

    public function app_collaborations(): BelongsToMany
    {
        return $this->belongsToMany(App::class, 'app_collaborators')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function app_setup_tokens(): HasMany
    {
        return $this->hasMany(AppSetupToken::class);
    }

    public function log(): MorphMany
    {
        return $this->morphMany(LogEntry::class, 'loggable');
    }
}
