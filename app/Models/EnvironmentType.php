<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EnvironmentType extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return HasMany<Environment, $this> */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }
}
