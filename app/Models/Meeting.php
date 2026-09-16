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
        'agenda_items',
        'decisions',
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
        'mom_content',
        'mom_status',
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
        'recurrence_rule' => 'array',
        'agenda_items' => 'array',
        'decisions' => 'array',
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

    // "Action items" from a meeting are plain Task rows (tasks.meeting_id) —
    // see tasks() above. There is deliberately no separate action-items
    // table; agenda/decisions live as JSON columns on this model instead of
    // their own tables (see the meeting-management improvement plan).

    public function histories()
    {
        return $this->hasMany(MeetingHistory::class)->orderByDesc('created_at');
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

        MeetingHistory::record(
            $this,
            'status_changed',
            "Meeting status changed from {$oldStatus} to {$status}",
            ['status' => $oldStatus],
            ['status' => $status]
        );

        return $this;
    }

    /**
     * Whether $user can edit/reschedule/cancel/delete this meeting, per the
     * `meetings` RBAC grants in config/rbac.php: company scope (admin/hr)
     * can edit any meeting; own scope (manager/employee) only their own
     * (creator), matching the config's documented "meeting edit => own:
     * any meeting creator could already update/cancel their own meeting."
     */
    public function isEditableBy($user): bool
    {
        $scope = app(\App\Services\RbacService::class)->scopeFor($user, 'meetings', 'edit');

        if ($scope === null) {
            return false;
        }
        if ($scope === 'company') {
            return true;
        }

        // own / team both collapse to "creator only" here — there's no
        // single reporting-line owner for a many-to-many meeting.
        return (int) $this->created_by === (int) $user->id;
    }

    /**
     * MOM authoring is a special case of edit: anyone who can edit the
     * meeting can author its minutes, PLUS a participant explicitly
     * designated as MOM writer (is_mom_writer) even if they didn't create
     * it — the flag already exists in the data model but nothing enforced
     * it before.
     */
    public function isMomAuthorableBy($user): bool
    {
        if ($this->isEditableBy($user)) {
            return true;
        }

        return $this->participants()
            ->where('user_id', $user->id)
            ->where('is_mom_writer', true)
            ->exists();
    }

    /**
     * Whether $user can view this meeting: company scope (admin/hr) sees
     * everything; own scope (manager/employee) is participant-based per
     * config/rbac.php's comment ("meetings => own (participant-only)").
     */
    public function isViewableBy($user): bool
    {
        $scope = app(\App\Services\RbacService::class)->scopeFor($user, 'meetings', 'view');

        if ($scope === null) {
            return false;
        }
        if ($scope === 'company') {
            return true;
        }

        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        return $this->participants()->where('user_id', $user->id)->exists();
    }

    /**
     * The field-value shape the Edit drawer's JS (openEditMeetingDrawer in
     * meeting/index.blade.php) expects on a data-meeting attribute, so it
     * can repopulate the shared edit drawer without an extra AJAX round
     * trip. Shared by index() (one per row) and show() (the fragment's own
     * Edit button) so this shape is defined in exactly one place.
     */
    public function toEditPayload(): array
    {
        $this->loadMissing('participants.user');

        return [
            'title' => $this->title,
            'description' => $this->description,
            'meeting_type' => $this->meeting_type,
            'location' => $this->location,
            'virtual_meeting_link' => $this->virtual_meeting_link,
            'meeting_date' => \Carbon\Carbon::parse($this->meeting_date)->format('Y-m-d'),
            'start_time' => \Carbon\Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => \Carbon\Carbon::parse($this->end_time)->format('H:i'),
            'reminder_minutes' => $this->reminder_minutes_before,
            'agenda_items' => $this->agenda_items ?? [],
            'participants' => $this->participants->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name ?? ('#' . $p->user_id),
                'email' => $p->user->email ?? '',
            ])->values(),
            'mom_writer_id' => $this->participants->firstWhere('is_mom_writer', true)?->user_id,
            'form_action' => route('meetings.update', $this->id),
            'editable' => $this->status === 'scheduled',
        ];
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