<?php
// app/Notifications/PerformanceNotification.php

namespace App\Notifications;

use App\Models\EmployeeKpiScore;
use App\Models\ManagerPerformanceReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * DB-only half of the dual-channel (FCM + DB) pattern established by
 * AttendanceNotification — the FCM push itself is sent separately by
 * PerformanceNotificationService::sendNotification(), mirroring
 * AttendanceNotificationService exactly.
 */
class PerformanceNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $type,
        private ?ManagerPerformanceReview $review = null,
        private ?EmployeeKpiScore $kpiScore = null,
    ) {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $title = '';
        $message = '';
        $data = ['type' => 'performance_' . $this->type];

        switch ($this->type) {
            case 'review_submitted':
                $title = 'Performance Review Available';
                $message = 'Your manager has submitted your performance review for '
                    . $this->review->review_month->format('F Y') . '.';
                $data['review_id'] = $this->review->id;
                $data['review_month'] = $this->review->review_month->format('Y-m-d');
                $data['overall_rating'] = $this->review->overall_rating;
                break;

            case 'monthly_score_ready':
                $title = 'Monthly Performance Score Ready';
                $message = 'Your performance score for '
                    . \Carbon\Carbon::parse($this->kpiScore->reporting_month)->format('F Y')
                    . ' is ready: ' . ($this->kpiScore->overall_score ?? 'N/A')
                    . '% (' . ($this->kpiScore->grade ?? 'N/A') . ').';
                $data['kpi_score_id'] = $this->kpiScore->id;
                $data['reporting_month'] = \Carbon\Carbon::parse($this->kpiScore->reporting_month)->format('Y-m-d');
                $data['overall_score'] = $this->kpiScore->overall_score;
                $data['grade'] = $this->kpiScore->grade;
                break;
        }

        return array_merge($data, [
            'title' => $title,
            'message' => $message,
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
