<?php

namespace App\Models;

use App\Observers\VariableObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([VariableObserver::class])]
class Variable extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['latest_version'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['latest_version'];

    /** @return Attribute<VariableVersion|null, never> */
    protected function latestVersion(): Attribute
    {
        return Attribute::make(get: fn (): ?VariableVersion => $this->versions()->latest()->first());
    }

    /**
     * @return HasMany<VariableVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(VariableVersion::class);
    }

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
