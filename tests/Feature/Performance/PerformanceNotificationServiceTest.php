<?php

namespace Tests\Feature\Performance;

use App\Models\EmployeeKpiScore;
use App\Models\ManagerPerformanceReview;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerformanceNotificationService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Dual-channel (FCM + DB) notifications for Performance — mirrors
 * AttendanceNotificationService's pattern. FirebaseService no-ops in the
 * test environment (no credentials file), so these only assert the DB
 * ('database' notification channel) half, which is the half the mobile
 * app's /notifications list actually reads.
 */
class PerformanceNotificationServiceTest extends TestCase
{
    private int $tenantId;
    private int $userId;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');
        $this->userId = (int) \DB::table('users')
            ->where('tenant_id', $this->tenantId)->where('status', '1')
            ->value('id');
        $this->user = User::withoutGlobalScopes()->find($this->userId);

        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    protected function tearDown(): void
    {
        \DB::table('notifications')
            ->where('notifiable_id', $this->userId)
            ->where('notifiable_type', User::class)
            ->whereIn('type', ['App\\Notifications\\PerformanceNotification'])
            ->where('created_at', '>=', now()->subMinute())
            ->delete();

        parent::tearDown();
    }

    public function test_review_submitted_notification_is_stored_in_the_database_channel(): void
    {
        $review = new ManagerPerformanceReview([
            'id' => 999999999,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'review_month' => Carbon::now()->startOfMonth(),
            'overall_rating' => 4,
            'status' => 'submitted',
        ]);
        $review->exists = true;

        app(PerformanceNotificationService::class)->notifyReviewSubmitted($review, $this->user);

        $stored = $this->user->notifications()
            ->where('type', 'App\\Notifications\\PerformanceNotification')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame('performance_review_submitted', $stored->data['type']);
    }

    public function test_monthly_score_ready_notification_is_stored_in_the_database_channel(): void
    {
        $kpiScore = new EmployeeKpiScore([
            'id' => 999999998,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'reporting_month' => Carbon::now()->startOfMonth()->subMonth(),
            'overall_score' => 82.5,
            'grade' => 'B+',
        ]);
        $kpiScore->exists = true;

        app(PerformanceNotificationService::class)->notifyMonthlyScoreReady($kpiScore, $this->user);

        $stored = $this->user->notifications()
            ->where('type', 'App\\Notifications\\PerformanceNotification')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame('performance_monthly_score_ready', $stored->data['type']);
        // 'decimal:2' cast on EmployeeKpiScore.overall_score returns a
        // formatted string, not a float.
        $this->assertSame('82.50', $stored->data['overall_score']);
    }
}
