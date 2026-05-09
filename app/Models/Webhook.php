<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'all_apps' => 'boolean',
    ];

    protected $hidden = ['secret'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<WebhookSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Does this webhook care about the given event and target?
     */
    public function matches(string $event, ?App $app, ?Environment $environment): bool
    {
        if (!$this->active) {
            return false;
        }

        if (!in_array($event, $this->events ?? [], true)) {
            return false;
        }

        if ($this->all_apps) {
            return true;
        }

        foreach ($this->subscriptions as $sub) {
            if ($sub->subscribable_type === App::class && $app && $sub->subscribable_id === $app->id) {
                return true;
            }

            if ($sub->subscribable_type === Environment::class && $environment && $sub->subscribable_id === $environment->id) {
                return true;
            }

            if ($sub->subscribable_type === EnvironmentType::class && $environment && $sub->subscribable_id === $environment->environment_type_id) {
                return true;
            }
        }

        return false;
    }

    /** @return Attribute<string, never> */
    protected function maskedUrl(): Attribute
    {
        return Attribute::get(function () {
            $parts = parse_url($this->url);
            if (!$parts || !isset($parts['host'])) {
                return $this->url;
            }

            return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . ($parts['path'] ?? '');
        });
    }
}
