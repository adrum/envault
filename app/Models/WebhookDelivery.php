<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
    ];

    /** @return BelongsTo<Webhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function isSuccess(): bool
    {
        return $this->response_status !== null
            && $this->response_status >= 200
            && $this->response_status < 300;
    }
}
