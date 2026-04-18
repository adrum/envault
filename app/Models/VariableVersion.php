<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VariableVersion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    /** @return Attribute<string, string> */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => decrypt($value),
            set: fn (string $value): string => encrypt($value),
        );
    }

    /** @return BelongsTo<Variable, $this> */
    public function variable(): BelongsTo
    {
        return $this->belongsTo(Variable::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
