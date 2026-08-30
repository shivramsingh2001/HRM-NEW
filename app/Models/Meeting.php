<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantTrait;

class Meeting extends Model
{
    use TenantTrait, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'meeting_id',
        'title',
        'description',
        'agenda',
        'meeting_type',
        'meeting_mode',
        'virtual_meeting_link',
        'location',
        'meeting_date',
        'start_time',
        'end_time',
        'created_by',
        'status',
        'cancellation_reason',
        'reminder_minutes_before',
        'reminder_sent',
        'parent_meeting_id',
        'recurrence_pattern',
        'recurrence_rule'
    ];

    protected $casts = [
        // 'meeting_date' => 'date',
        // 'start_time' => 'datetime:H:i',
        // 'end_time' => 'datetime:H:i',
        'reminder_sent' => 'boolean',
        'recurrence_rule' => 'array'
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentMeeting()
    {
        return $this->belongsTo(Meeting::class, 'parent_meeting_id');
    }

    public function childMeetings()
    {
        return $this->hasMany(Meeting::class, 'parent_meeting_id');
    }

    public function participants()
    {
        return $this->hasMany(MeetingParticipant::class);
    }
    public function tasks()
    {
        return $this->hasMany(Task::class,'meeting_id');
    }

    public function agendas()
    {
        return $this->hasMany(MeetingAgenda::class);
    }

    public function minutes()
    {
        return $this->hasMany(MeetingMinute::class);
    }

    public function actionItems()
    {
        return $this->hasMany(MeetingActionItem::class);
    }

    public function histories()
    {
        return $this->hasMany(MeetingHistory::class);
    }

    // Mom Writers Relationship - FIXED
    public function momWriters()
    {
        return $this->hasMany(MeetingParticipant::class, 'meeting_id')->where('is_mom_writer', true);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('meeting_date', '>=', now()->toDateString())
            ->where('status', 'scheduled');
    }

    public function scopeToday($query)
    {
        return $query->where('meeting_date', now()->toDateString());
    }

    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->orWhere('created_by', $userId);
    }

    // Accessors
    public function getIsOngoingAttribute()
    {
        if ($this->status !== 'scheduled') return false;

        $now = now();
        $meetingDateTime = $this->meeting_date->format('Y-m-d') . ' ' . $this->start_time;
        $meetingEndDateTime = $this->meeting_date->format('Y-m-d') . ' ' . $this->end_time;

        return $now >= $meetingDateTime && $now <= $meetingEndDateTime;
    }

    public function getFormattedDateTimeAttribute()
    {
        return $this->meeting_date->format('d M, Y') . ' | ' .
            $this->start_time->format('h:i A') . ' - ' .
            $this->end_time->format('h:i A');
    }

    // Helper Methods
    public function addParticipant($userId, $role = 'attendee', $isMomWriter = false)
    {
        return $this->participants()->updateOrCreate(
            ['user_id' => $userId],
            ['role' => $role, 'is_mom_writer' => $isMomWriter]
        );
    }

    public function updateStatus($status, $reason = null)
    {
        $oldStatus = $this->status;
        $this->status = $status;

        if ($status === 'cancelled' && $reason) {
            $this->cancellation_reason = $reason;
        }

        $this->save();

        // Log history
        MeetingHistory::create([
            'meeting_id' => $this->id,
            'action_by' => auth()->id(),
            'action_type' => 'status_changed',
            'old_values' => json_encode(['status' => $oldStatus]),
            'new_values' => json_encode(['status' => $status]),
            'description' => "Meeting status changed from {$oldStatus} to {$status}"
        ]);

        return $this;
    }

    public function getParticipantCounts()
    {
        return [
            'total' => $this->participants()->count(),
            'confirmed' => $this->participants()->where('attendance_status', 'confirmed')->count(),
            'declined' => $this->participants()->where('attendance_status', 'declined')->count(),
            'pending' => $this->participants()->where('attendance_status', 'pending')->count(),
            'present' => $this->participants()->where('attendance_status', 'present')->count(),
            'mom_writers' => $this->participants()->where('is_mom_writer', true)->count()
        ];
    }

    // Safe count helper methods
    public function getParticipantsCountAttribute()
    {
        return $this->participants ? $this->participants->count() : 0;
    }

    public function getMomWritersCountAttribute()
    {
        return $this->momWriters ? $this->momWriters->count() : 0;
    }

    public function getHasMomWritersAttribute()
    {
        return $this->momWriters && $this->momWriters->count() > 0;
    }

    // Get all participants with their user details
    public function getParticipantsWithUsersAttribute()
    {
        return $this->participants()->with('user')->get();
    }
}