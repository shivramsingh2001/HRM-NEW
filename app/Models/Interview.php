<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Interview extends Model
{
     use TenantTrait;
    protected $table = 'interviews';

    protected $fillable = [
        'tenant_id',
        'interview_code',
        'job_application_id',
        'candidate_id',
        'recruitment_stage_id',
        'interview_round',
        'round_name',
        'interview_type',
        'interviewer_id',
        'co_interviewer_ids',
        'scheduled_date',
        'scheduled_time',
        'duration_minutes',
        'meeting_link',
        'location',
        'instructions',
        'reminder_sent',
        'status',
        'outcome',
        'feedback',
        'rating',
        'rescheduled_from',
        'reschedule_reason',
        'cancelled_by',
        'cancellation_reason',
        'completed_at',
        'created_by'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i:s',
        'co_interviewer_ids' => 'array',
        'duration_minutes' => 'integer',
        'reminder_sent' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RESCHEDULED = 'rescheduled';
    const STATUS_NO_SHOW = 'no_show';

    // Outcome constants
    const OUTCOME_SELECTED = 'selected';
    const OUTCOME_REJECTED = 'rejected';
    const OUTCOME_NEXT_ROUND = 'next_round';
    const OUTCOME_ON_HOLD = 'on_hold';

    // Interview type constants
    const TYPE_ONLINE = 'online';
    const TYPE_OFFLINE = 'offline';
    const TYPE_TELEPHONIC = 'telephonic';
    const TYPE_VIDEO = 'video';

    public static $statuses = [
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_RESCHEDULED => 'Rescheduled',
        self::STATUS_NO_SHOW => 'No Show'
    ];

    public static $outcomes = [
        self::OUTCOME_SELECTED => 'Selected',
        self::OUTCOME_REJECTED => 'Rejected',
        self::OUTCOME_NEXT_ROUND => 'Next Round',
        self::OUTCOME_ON_HOLD => 'On Hold'
    ];

    public static $interviewTypes = [
        self::TYPE_ONLINE => 'Online',
        self::TYPE_OFFLINE => 'Offline',
        self::TYPE_TELEPHONIC => 'Telephonic',
        self::TYPE_VIDEO => 'Video'
    ];

    // Relationships
    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interviewer()
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function coInterviewers()
    {
        if ($this->co_interviewer_ids) {
            return User::whereIn('id', $this->co_interviewer_ids)->get();
        }
        return collect();
    }

    public function recruitmentStage()
    {
        return $this->belongsTo(RecruitmentStage::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(InterviewFeedback::class);
    }

    public function latestFeedback()
    {
        return $this->hasOne(InterviewFeedback::class)->latest();
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(Interview::class, 'rescheduled_from');
    }

    public function rescheduledTo()
    {
        return $this->hasOne(Interview::class, 'rescheduled_from');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Accessors
    public function getScheduledDateTimeAttribute()
    {
        return $this->scheduled_date->format('Y-m-d') . ' ' . $this->scheduled_time->format('H:i:s');
    }

    public function getEndTimeAttribute()
    {
        return $this->scheduled_time->copy()->addMinutes($this->duration_minutes);
    }

    public function getFormattedScheduledDateAttribute()
    {
        return $this->scheduled_date->format('d M Y');
    }

    public function getFormattedScheduledTimeAttribute()
    {
        return $this->scheduled_time->format('h:i A');
    }

    public function getFormattedEndTimeAttribute()
    {
        return $this->end_time->format('h:i A');
    }

    public function getInterviewTypeLabelAttribute()
    {
        return self::$interviewTypes[$this->interview_type] ?? ucfirst($this->interview_type);
    }

    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getOutcomeLabelAttribute()
    {
        return self::$outcomes[$this->outcome] ?? ucfirst($this->outcome);
    }

    public function getLocationOrLinkAttribute()
    {
        if ($this->interview_type === self::TYPE_ONLINE || $this->interview_type === self::TYPE_VIDEO) {
            return $this->meeting_link;
        }
        return $this->location;
    }

    // Scopes
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_date', '>=', now()->format('Y-m-d'));
    }

    public function scopeToday($query)
    {
        return $query->where('scheduled_date', now()->format('Y-m-d'));
    }

    public function scopeByInterviewer($query, $interviewerId)
    {
        return $query->where('interviewer_id', $interviewerId);
    }

    // Helper methods
    public function markAsCompleted($outcome, $feedback = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'outcome' => $outcome,
            'feedback' => $feedback,
            'completed_at' => now()
        ]);

        // Update application stage based on outcome
        if ($outcome === self::OUTCOME_SELECTED) {
            $this->application->moveToStage(JobApplication::STAGE_INTERVIEW_COMPLETED, 
                "Candidate selected in {$this->round_name}");
        } elseif ($outcome === self::OUTCOME_REJECTED) {
            $this->application->moveToStage(JobApplication::STAGE_REJECTED, 
                "Candidate rejected in {$this->round_name}");
        } elseif ($outcome === self::OUTCOME_NEXT_ROUND) {
            // Stay in interview stage for next round
        }
    }

    public function cancel($reason)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason
        ]);
    }

    public function reschedule($newDate, $newTime, $reason)
    {
        $newInterview = $this->replicate();
        $newInterview->fill([
            'scheduled_date' => $newDate,
            'scheduled_time' => $newTime,
            'status' => self::STATUS_SCHEDULED,
            'rescheduled_from' => $this->id,
            'reschedule_reason' => $reason,
            'created_by' => auth()->id()
        ]);
        $newInterview->save();

        $this->update([
            'status' => self::STATUS_RESCHEDULED,
            'cancellation_reason' => "Rescheduled to {$newDate} at {$newTime}. Reason: {$reason}"
        ]);

        return $newInterview;
    }

    public function sendReminder()
    {
        if (!$this->reminder_sent) {
            // Send reminder logic here
            $this->update(['reminder_sent' => true]);
        }
    }
}