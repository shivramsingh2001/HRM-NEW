<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery attempt of a ReportSchedule (sent / failed), shown in its run log.
 *
 * @property int $id
 * @property int $report_schedule_id
 * @property string $trigger
 * @property string $status
 * @property string|null $period_label
 * @property string|null $file_name
 * @property int|null $file_size
 * @property array<int, string>|null $recipients
 * @property string|null $error
 * @property \Carbon\CarbonInterface $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 */
class ReportScheduleRun extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'report_schedule_id', 'trigger', 'status', 'period_label', 'file_name',
        'file_size', 'recipients', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return BelongsTo<ReportSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id');
    }
}
