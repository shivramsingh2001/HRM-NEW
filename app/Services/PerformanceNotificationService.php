<?php
// app/Services/PerformanceNotificationService.php

namespace App\Services;

use App\Models\EmployeeKpiScore;
use App\Models\ManagerPerformanceReview;
use App\Models\User;
use App\Notifications\PerformanceNotification;
use Illuminate\Support\Facades\Log;

/**
 * Dual-channel (FCM push + DB) notifications for Performance, mirroring
 * AttendanceNotificationService's exact pattern: a raw FCM send via
 * FirebaseService plus a `->notify()` database record via a Notification
 * class, rather than a single multi-channel Notification (`via()` only ever
 * returns ['database'] here, same as AttendanceNotification).
 */
class PerformanceNotificationService
{
    public function __construct(private FirebaseService $firebaseService)
    {
    }

    /**
     * Notify an employee that their manager submitted/updated a review for
     * them. Called only on the draft->submitted (or re-submitted) transition
     * — see ManagerPerformanceReviewController::store()/update().
     */
    public function notifyReviewSubmitted(ManagerPerformanceReview $review, User $employee): bool
    {
        try {
            $title = 'Performance Review Available';
            $body = 'Your manager submitted your performance review for '
                . $review->review_month->format('F Y') . '.';

            $this->sendNotification($employee, $title, $body, [
                'type' => 'performance_review_submitted',
                'review_id' => $review->id,
                'review_month' => $review->review_month->format('Y-m-d'),
            ]);

            $employee->notify(new PerformanceNotification('review_submitted', review: $review));

            return true;
        } catch (\Throwable $e) {
            Log::error('Performance review-submitted notification failed', [
                'user_id' => $employee->id,
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Notify an employee that their monthly performance score has been
     * calculated. Called only when the caller explicitly opts in (the
     * scheduled `performance:rollup-monthly --notify` run) — manual/backfill
     * reruns over historical months don't spam employees.
     */
    public function notifyMonthlyScoreReady(EmployeeKpiScore $kpiScore, User $employee): bool
    {
        try {
            $month = \Carbon\Carbon::parse($kpiScore->reporting_month)->format('F Y');
            $title = 'Monthly Performance Score Ready';
            $body = "Your performance score for {$month} is ready: "
                . ($kpiScore->overall_score ?? 'N/A') . '% (' . ($kpiScore->grade ?? 'N/A') . ').';

            $this->sendNotification($employee, $title, $body, [
                'type' => 'performance_monthly_score_ready',
                'kpi_score_id' => $kpiScore->id,
                'reporting_month' => \Carbon\Carbon::parse($kpiScore->reporting_month)->format('Y-m-d'),
            ]);

            $employee->notify(new PerformanceNotification('monthly_score_ready', kpiScore: $kpiScore));

            return true;
        } catch (\Throwable $e) {
            Log::error('Performance monthly-score-ready notification failed', [
                'user_id' => $employee->id,
                'kpi_score_id' => $kpiScore->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Core FCM push — identical token-decoding/looping logic to
     * AttendanceNotificationService::sendNotification().
     */
    private function sendNotification(User $user, string $title, string $body, array $data = []): bool
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

            $result = $this->firebaseService->sendToDevice($token, $title, $body, $data);

            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }
}
