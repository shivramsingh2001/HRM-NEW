<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Email this report on a schedule" rule (Reports → Scheduled Reports).
 * The report is generated as `created_by`; see App\Services\Reports\ReportScheduleRunner.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $created_by
 * @property string $name
 * @property string $report_key
 * @property string|null $period
 * @property array<string, mixed>|null $filters
 * @property string $frequency
 * @property array<int, int>|null $weekdays
 * @property int|null $day_of_month
 * @property string $send_time
 * @property array<int, string> $recipients
 * @property string|null $subject
 * @property string|null $message
 * @property bool $is_active
 * @property string|null $paused_reason
 * @property \Carbon\CarbonInterface|null $next_run_at
 * @property \Carbon\CarbonInterface|null $run_requested_at
 * @property \Carbon\CarbonInterface|null $last_run_at
 * @property string|null $last_status
 * @property string|null $last_error
 */
class ReportSchedule extends Model
{
    use TenantTrait;

    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /** Runs kept per schedule; older ones are pruned after each run. */
    public const RUNS_KEPT = 50;

    protected $fillable = [
        'tenant_id', 'created_by', 'name', 'report_key', 'period', 'filters', 'frequency',
        'weekdays', 'day_of_month', 'send_time', 'recipients', 'subject', 'message',
        'is_active', 'paused_reason', 'next_run_at', 'run_requested_at', 'last_run_at',
        'last_status', 'last_error',
    ];

    protected $casts = [
        'filters' => 'array',
        'weekdays' => 'array',
        'recipients' => 'array',
        'day_of_month' => 'integer',
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'run_requested_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ReportScheduleRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ReportScheduleRun::class)->orderByDesc('id');
    }
}
