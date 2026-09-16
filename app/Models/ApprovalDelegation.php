<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDelegation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'request_types' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function coversToday(string $requestType): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $today = now()->startOfDay();
        if ($today->lt($this->starts_on) || $today->gt($this->ends_on)) {
            return false;
        }
        $types = $this->request_types;

        return empty($types) || in_array($requestType, $types, true);
    }
}
