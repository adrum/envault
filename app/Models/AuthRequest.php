<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthRequest extends Model
{
    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = ['token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
