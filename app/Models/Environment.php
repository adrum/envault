<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Environment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = [];

    protected $with = ['environmentType'];

    /** @return Attribute<string, never> */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => $this->custom_label ?? $this->environmentType->name ?? 'Default');
    }

    /** @return Attribute<string, never> */
    protected function color(): Attribute
    {
        return Attribute::get(fn () => $this->environmentType->color ?? 'gray');
    }

    /** @return BelongsTo<EnvironmentType, $this> */
    public function environmentType(): BelongsTo
    {
        return $this->belongsTo(EnvironmentType::class);
    }

    /** @return BelongsTo<App, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /** @return HasMany<Variable, $this> */
    public function variables(): HasMany
    {
        return $this->hasMany(Variable::class)->orderBy('sort_order');
    }
}
