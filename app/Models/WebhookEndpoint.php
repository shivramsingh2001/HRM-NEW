<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'disabled_at' => 'datetime',
    ];

    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
