<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Platform-wide maintenance switch — one row, managed from the Super Admin
 * Panel. Not tenant-scoped (it covers every company).
 *
 * @property int $id
 * @property bool $is_enabled
 * @property string|null $title
 * @property string|null $message
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property array<int, string>|null $allowed_ips
 * @property array<int, int>|null $allowed_users
 * @property int|null $enabled_by
 */
class MaintenanceMode extends Model
{
    public const DEFAULT_TITLE = 'Under Maintenance';

    public const DEFAULT_MESSAGE = 'We are currently performing scheduled maintenance. Please check back soon.';

    protected $fillable = [
        'is_enabled', 'title', 'message', 'start_time', 'end_time',
        'allowed_ips', 'allowed_users', 'enabled_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'allowed_ips' => 'array',
            'allowed_users' => 'array',
        ];
    }

    /**
     * Enabled AND inside the optional start/end window. A window that has
     * not started yet is "scheduled"; one that has ended switches itself off
     * without anyone touching the row.
     */
    public function isActive(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->is_enabled
            && ($this->start_time === null || $now->gte($this->start_time))
            && ($this->end_time === null || $now->lt($this->end_time));
    }
}
