<?php
// app/Models/MeetingParticipant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'meeting_id', 'user_id', 'role', 'is_mom_writer',
        'attendance_status', 'reminder_sent',
    ];

    protected $casts = [
        'is_mom_writer' => 'boolean',
        'reminder_sent' => 'boolean',
        'responded_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime'
    ];

    // Relationships
    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeMomWriters($query)
    {
        return $query->where('is_mom_writer', true);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('attendance_status', 'confirmed');
    }

    // Methods
    public function confirmAttendance($comments = null)
    {
        $this->attendance_status = 'confirmed';
        $this->response_comments = $comments;
        $this->responded_at = now();
        $this->save();
        
        return $this;
    }

    public function markPresent()
    {
        $this->attendance_status = 'present';
        $this->joined_at = now();
        $this->save();
        
        return $this;
    }

    public function markAbsent()
    {
        $this->attendance_status = 'absent';
        $this->save();
        
        return $this;
    }
}