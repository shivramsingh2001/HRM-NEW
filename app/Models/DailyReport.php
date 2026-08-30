<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReport extends Model
{
    use HasFactory;

    protected $table = 'daily_reports';

    protected $fillable = [
        'request_id',
        'user_id',
        'report_date',
        'work_done',
        'challenges_faced',
        'next_day_plan',
        'start_time',
        'end_time',
        'total_hours',
        'status',
        'manager_comments'
    ];

    protected $casts = [
        'report_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    const STATUS_DRAFT = 'DRAFT';
    const STATUS_SUBMITTED = 'SUBMITTED';

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('report_date', $date);
    }

    // Accessors
    public function getFormattedTotalHoursAttribute(): string
    {
        if ($this->total_hours) {
            $hours = floor($this->total_hours / 60);
            $minutes = $this->total_hours % 60;
            return sprintf("%02d:%02d", $hours, $minutes);
        }
        return '00:00';
    }

    // Methods
    public function calculateTotalHours(): void
    {
        if ($this->start_time && $this->end_time) {
            $start = \Carbon\Carbon::parse($this->start_time);
            $end = \Carbon\Carbon::parse($this->end_time);
            $this->total_hours = $end->diffInMinutes($start);
        }
    }
}