<?php
// app/Services/MeetingNotificationService.php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\MeetingNotification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MeetingNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when meeting is created
     */
    public function notifyMeetingCreated(Meeting $meeting)
    {
        try {
            $participants = $meeting->participants()->with('user')->get();
            
            if ($participants->isEmpty()) {
                Log::warning('No participants found for meeting', ['meeting_id' => $meeting->id]);
                return false;
            }

            $creator = $meeting->creator;
            
            $data = [
                'meeting_id' => $meeting->id,
                'meeting_code' => $meeting->meeting_id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date,
                'meeting_date_formatted' => Carbon::parse($meeting->meeting_date)->format('d M Y'),
                'start_time' => $meeting->start_time,
                'end_time' => $meeting->end_time,
                'duration_minutes' => $meeting->duration_minutes,
                'meeting_type' => $meeting->meeting_type,
                'location' => $meeting->location,
                'virtual_link' => $meeting->virtual_meeting_link,
                'type' => 'meeting_created'
            ];

            $title = '📅 New Meeting Scheduled';
            $body = $creator->name . ' scheduled a meeting: "' . $meeting->title . '" on ' . 
                    Carbon::parse($meeting->meeting_date)->format('d M Y') . ' at ' . $meeting->start_time;

            foreach ($participants as $participant) {
                $user = $participant->user;
                if ($user) {
                    $this->sendNotification($user, $title, $body, array_merge($data, [
                        'participant_role' => $participant->role,
                        'is_mom_writer' => $participant->is_mom_writer
                    ]));
                    $user->notify(new MeetingNotification($meeting, 'created'));
                }
            }

            Log::info('Meeting creation notifications sent', [
                'meeting_id' => $meeting->id,
                'participant_count' => $participants->count()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting creation notifications', [
                'error' => $e->getMessage(),
                'meeting_id' => $meeting->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when meeting is updated
     */
    public function notifyMeetingUpdated(Meeting $meeting, $changedFields = [])
    {
        try {
            $participants = $meeting->participants()->with('user')->get();
            
            if ($participants->isEmpty()) {
                return false;
            }

            $updater = auth()->user();
            
            $data = [
                'meeting_id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date,
                'start_time' => $meeting->start_time,
                'end_time' => $meeting->end_time,
                'changed_fields' => $changedFields,
                'type' => 'meeting_updated'
            ];

            $title = '✏️ Meeting Updated';
            $body = 'Meeting "' . $meeting->title . '" has been updated by ' . $updater->name;

            foreach ($participants as $participant) {
                $user = $participant->user;
                if ($user) {
                    $this->sendNotification($user, $title, $body, $data);
                    $user->notify(new MeetingNotification($meeting, 'updated', json_encode($changedFields)));
                }
            }

            // Also notify creator if not a participant
            $creator = $meeting->creator;
            if ($creator && !$participants->contains('user_id', $creator->id)) {
                $this->sendNotification($creator, $title, $body, $data);
                $creator->notify(new MeetingNotification($meeting, 'updated'));
            }

            Log::info('Meeting update notifications sent', [
                'meeting_id' => $meeting->id,
                'participant_count' => $participants->count()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting update notifications', [
                'error' => $e->getMessage(),
                'meeting_id' => $meeting->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when meeting is cancelled
     */
    public function notifyMeetingCancelled(Meeting $meeting, $reason = null)
    {
        try {
            $participants = $meeting->participants()->with('user')->get();
            
            if ($participants->isEmpty()) {
                return false;
            }

            $canceller = auth()->user();
            
            $data = [
                'meeting_id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date,
                'cancellation_reason' => $reason,
                'cancelled_by' => $canceller->name,
                'type' => 'meeting_cancelled'
            ];

            $title = '❌ Meeting Cancelled';
            $body = 'Meeting "' . $meeting->title . '" scheduled for ' . 
                    Carbon::parse($meeting->meeting_date)->format('d M Y') . 
                    ' has been cancelled.';

            if ($reason) {
                $body .= ' Reason: ' . $reason;
            }

            foreach ($participants as $participant) {
                $user = $participant->user;
                if ($user) {
                    $this->sendNotification($user, $title, $body, $data);
                    $user->notify(new MeetingNotification($meeting, 'cancelled', $reason));
                }
            }

            Log::info('Meeting cancellation notifications sent', [
                'meeting_id' => $meeting->id,
                'participant_count' => $participants->count()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting cancellation notifications', [
                'error' => $e->getMessage(),
                'meeting_id' => $meeting->id
            ]);
            return false;
        }
    }

    /**
     * Send reminder for upcoming meetings
     */
    public function sendMeetingReminders()
    {
        try {
            $now = Carbon::now();
            
            // Get meetings that are scheduled and have reminder time
            $meetings = Meeting::where('status', 'scheduled')
                ->where('meeting_date', '>=', $now->toDateString())
                ->where(function ($q) use ($now) {
                    $q->whereRaw("CONCAT(meeting_date, ' ', start_time) >= ?", [$now->toDateTimeString()]);
                })
                ->get();

            $reminderCount = 0;

            foreach ($meetings as $meeting) {
                $meetingDateTime = Carbon::parse($meeting->meeting_date . ' ' . $meeting->start_time);
                $minutesUntilMeeting = $now->diffInMinutes($meetingDateTime, false);
                
                // Send reminder if meeting is within reminder minutes
                if ($minutesUntilMeeting > 0 && $minutesUntilMeeting <= ($meeting->reminder_minutes_before ?? 15)) {
                    $participants = $meeting->participants()->with('user')->get();
                    
                    $data = [
                        'meeting_id' => $meeting->id,
                        'title' => $meeting->title,
                        'meeting_date' => $meeting->meeting_date,
                        'start_time' => $meeting->start_time,
                        'location' => $meeting->location,
                        'virtual_link' => $meeting->virtual_meeting_link,
                        'minutes_until' => $minutesUntilMeeting,
                        'type' => 'meeting_reminder'
                    ];

                    $title = '⏰ Meeting Reminder';
                    $body = 'Reminder: Meeting "' . $meeting->title . '" starts in ' . 
                            $minutesUntilMeeting . ' minutes at ' . $meeting->start_time;

                    foreach ($participants as $participant) {
                        $user = $participant->user;
                        if ($user) {
                            $this->sendNotification($user, $title, $body, $data);
                            $user->notify(new MeetingNotification($meeting, 'reminder', $minutesUntilMeeting));
                        }
                    }
                    
                    $reminderCount++;
                }
            }

            Log::info('Meeting reminders sent', ['count' => $reminderCount]);
            return $reminderCount;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting reminders', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Send notification when meeting minutes are added
     */
    public function notifyMinutesAdded(Meeting $meeting, $taskCount = 0)
    {
        try {
            $participants = $meeting->participants()->with('user')->get();
            
            if ($participants->isEmpty()) {
                return false;
            }

            $data = [
                'meeting_id' => $meeting->id,
                'title' => $meeting->title,
                'task_count' => $taskCount,
                'mom_content_preview' => substr($meeting->mom_content ?? '', 0, 100),
                'type' => 'meeting_minutes_added'
            ];

            $title = '📝 Meeting Minutes Added';
            $body = 'Meeting minutes have been added for "' . $meeting->title . '"';
            
            if ($taskCount > 0) {
                $body .= ' with ' . $taskCount . ' new task(s) created.';
            }

            foreach ($participants as $participant) {
                $user = $participant->user;
                if ($user) {
                    $this->sendNotification($user, $title, $body, $data);
                    $user->notify(new MeetingNotification($meeting, 'minutes_added'));
                }
            }

            Log::info('Meeting minutes notifications sent', [
                'meeting_id' => $meeting->id,
                'task_count' => $taskCount
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting minutes notifications', [
                'error' => $e->getMessage(),
                'meeting_id' => $meeting->id
            ]);
            return false;
        }
    }

    /**
     * Send notification to MOM writers
     */
    public function notifyMomWriters(Meeting $meeting)
    {
        try {
            $momWriters = $meeting->momWriters;
            
            if ($momWriters->isEmpty()) {
                return false;
            }

            $data = [
                'meeting_id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date,
                'meeting_date_formatted' => Carbon::parse($meeting->meeting_date)->format('d M Y'),
                'start_time' => $meeting->start_time,
                'type' => 'mom_writer_assigned'
            ];

            $title = '📝 MOM Writer Assignment';
            $body = 'You have been assigned as a Minutes of Meeting (MOM) writer for meeting "' . 
                    $meeting->title . '" on ' . 
                    Carbon::parse($meeting->meeting_date)->format('d M Y');

            foreach ($momWriters as $writer) {
                $this->sendNotification($writer, $title, $body, $data);
                $writer->notify(new MeetingNotification($meeting, 'created'));
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to notify MOM writers', [
                'error' => $e->getMessage(),
                'meeting_id' => $meeting->id
            ]);
            return false;
        }
    }

    /**
     * Core method to send FCM notification
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        $tokens = $user->fcm_tokens ?? [];
        
        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }
        
        if (!is_array($tokens) || empty($tokens)) {
            return false;
        }

        $successCount = 0;

        foreach ($tokens as $tokenData) {
            $token = is_array($tokenData) ? ($tokenData['token'] ?? '') : $tokenData;
            
            if (empty($token)) {
                continue;
            }
            
            $result = $this->firebaseService->sendToDevice(
                $token,
                $title,
                $body,
                $data
            );

            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }
}