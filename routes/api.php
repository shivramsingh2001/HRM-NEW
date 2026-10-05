<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Holiday\HolidayController;
use App\Http\Controllers\Api\Leave\LeaveController;
use App\Http\Controllers\Api\Expense\ExpenseController;
use App\Http\Controllers\Api\Announcement\AnnouncementController;
use App\Http\Controllers\Api\Attendance\AttendanceController;
use App\Http\Controllers\Api\FCM\FCMController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Payroll\PayrollController;
use App\Http\Controllers\Api\Shift\ShiftController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\Project\ProjectController;
use App\Http\Controllers\Api\Task\TaskController;
use App\Http\Controllers\Api\Attendance\LocationTrackingController;
use App\Http\Controllers\Api\Attendance\RequestController;
use App\Http\Controllers\Api\Attendance\OvertimeController;
use App\Http\Controllers\Api\Mom\MeetingController;
use App\Http\Controllers\Api\Loan\LoanController;
use App\Http\Controllers\Api\Offboarding\OffboardingController;
use App\Http\Controllers\Api\Performance\PerformanceController as ApiPerformanceController;

use App\Http\Controllers\AI\AnnoucementController as AIAnnoucementController;
use App\Http\Controllers\AI\AttendanceController as AIAttendanceController;
use App\Http\Controllers\AI\AttendanceRegularizationController as AIAttendanceRegularizationController;
use App\Http\Controllers\AI\ExpenseController as AIExpenseController;
use App\Http\Controllers\AI\HolidayController as AIHolidayController;
use App\Http\Controllers\AI\LeaveController as AILeaveController;
use App\Http\Controllers\AI\ProjectController as AIProjectController;
use App\Http\Controllers\AI\ShiftController as AIShiftController;
use App\Http\Controllers\AI\TaskController as AITaskController;
use App\Http\Controllers\AI\TeamController as AITeamController;
use App\Http\Controllers\AI\ProfileController as AIProfileController;
use App\Http\Controllers\AI\AttendanceLocationController as AIAttendanceLocationController;
use App\Http\Controllers\AI\RequestController as AIRequestController;
use App\Http\Controllers\AI\AssetController as AIAssetController;
use App\Http\Controllers\AI\LoanController as AILoanController;
use App\Http\Controllers\AI\OvertimeController as AIOvertimeController;
use App\Http\Controllers\AI\PayrollController as AIPayrollController;
use App\Http\Controllers\AI\ApprovalController as AIApprovalController;
use App\Http\Controllers\AI\LeaveHistoryController as AILeaveHistoryController;
use App\Http\Controllers\AI\PerformanceController as AIPerformanceController;
use App\Http\Controllers\AI\PolicyController as AIPolicyController;
use App\Http\Controllers\AI\OffboardingController as AIOffboardingController;
use App\Http\Controllers\AI\MeetingController as AIMeetingController;
use App\Http\Controllers\AI\DailyReportController as AIDailyReportController;
use App\Http\Controllers\AI\OnboardingController as AIOnboardingController;
use App\Http\Controllers\AI\RecruitmentController as AIRecruitmentController;
use Illuminate\Support\Facades\Route;


Route::middleware('tenant')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    //forgot Password
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:login');
    Route::post('/send-otp', [AuthController::class, 'otp'])->middleware('throttle:otp-request');
    Route::post('/login-otp', [AuthController::class, 'login_otp'])->middleware('throttle:otp-verify');

    Route::middleware(['auth:api', 'singleLogin'])->group(function () {
        
        Route::get('/verify-token', function (Request $request) {
            // Return authenticated user info
            return response()->json([
                'status' => 'success',
                'user' => Auth::user()
            ]);
        });
        Route::any('/logout/{fcm_token?}', [AuthController::class, 'logout']);
        Route::get('/delete', [AuthController::class, 'delete']);
        Route::post('/fcm/token', [FCMController::class, 'storeToken']);
        Route::delete('/fcm/token', [FCMController::class, 'removeToken']);
        //face Registration
        Route::get('/user/face-registration', [AuthController::class, 'face_register']);

        //Logout
        Route::post('/logout', [AuthController::class, 'logout']);
       
        
        //Change Password
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // Plan gating: every module group below carries `feature:<key>` (same keys as the
        // web routes). A module the company's plan doesn't include answers 403
        // {success:false, code:'feature_disabled'}. GET /user/features tells the app up front.
        Route::get('/user/features', [UserController::class, 'features']);

        //holiday
        Route::get('/holiday', [HolidayController::class, 'index'])->middleware('feature:holiday');
        Route::get('/fetch-all-user', [UserController::class, 'allusers']);

        //User Profile
        Route::get('/user/profile', [UserController::class, 'index']);

        // Today's birthdays + work anniversaries in the company (any signed-in user; not plan-gated)
        Route::get('/user/celebrations/today', [\App\Http\Controllers\Api\User\CelebrationController::class, 'today']);

        //Leave
        Route::middleware('feature:leave_management')->group(function () {
            Route::get('/leave-type', [LeaveController::class, 'fetch_type']);
            Route::get('/view-leave', [LeaveController::class, 'view']);
            Route::post('/apply-leave', [LeaveController::class, 'store']);

            Route::get('/view-team-leave', [LeaveController::class, 'view_all'])->middleware('permission:leave,view');
            Route::get('/update-team-leave-status/{id}', [LeaveController::class, 'updateLeaveStatus'])->middleware('permission:leave,approve');
        });

        //Announcement
        Route::middleware('feature:announcements')->group(function () {
            Route::get('/view-announcement', [AnnouncementController::class, 'view']);
            Route::post('/create-announcement', [AnnouncementController::class, 'store']);
            Route::get('/all-announcement', [AnnouncementController::class, 'index']);
            Route::post('/acknowledge-announcement/{id}', [AnnouncementController::class, 'acknowledge']);
        });

        //Expense
        Route::middleware('feature:expense_management')->group(function () {
            Route::get('/expense-type', [ExpenseController::class, 'fetch_type']);
            Route::get('/view-expense', [ExpenseController::class, 'view']);
            Route::get('/view-expense-payments', [ExpenseController::class, 'payments']);
            Route::post('/withdraw-expense', [ExpenseController::class, 'withdraw']);
            Route::post('/create-expense', [ExpenseController::class, 'store']);

            Route::get('/view-team-expense', [ExpenseController::class, 'view_all']);
            Route::post('/team/expense-update', [ExpenseController::class, 'updateStatus']);
        });

        //Attendance
        Route::middleware('feature:attendance')->group(function () {
            Route::post('/user/attendance/clock-in', [AttendanceController::class, 'clockIn']);
            Route::post('/user/attendance/clock-out', [AttendanceController::class, 'clockOut']);
            Route::get('/user/attendance/history', [AttendanceController::class, 'history']);
            Route::get('/user/attendance/today', [AttendanceController::class, 'getAttendance']);
            Route::get('/user/attendance/today-locations', [AttendanceController::class, 'todayLocations']);
            Route::get('/user/attendance/punches', [AttendanceController::class, 'punchHistory']);
            Route::get('/user/attendance/current-session', [AttendanceController::class, 'currentSession']);
        });

        // Location pings stay ungated: the tracking controllers already accept/skip per the
        // employee's own tracking switch, and a 403 here would make the app's background
        // sender retry forever.
        Route::post('/user/attendance/track', [AttendanceController::class, 'trackLocation'])->middleware('throttle:location-ingest');
        Route::post('/user/attendance/track-batch', [AttendanceController::class, 'trackBatch'])->middleware('throttle:location-ingest');
        Route::post('/location/status', [LocationTrackingController::class, 'locationStatus']);

        Route::middleware('feature:regularization')->group(function () {
            Route::post('/user/attendance/regularization', [AttendanceController::class, 'regularizationStore']);
            Route::get('/user/attendance/view-regularization', [AttendanceController::class, 'getMyRegularizations']);
            Route::get('/manager/attendance/view-regularization', [AttendanceController::class, 'getReporteesRegularizations']);
            Route::post('/manager/attendance/update-regularization-approval', [AttendanceController::class, 'regularizationApproval']);
        });

        //Performance (employee self-service — no team/manager endpoints here)
        Route::middleware('feature:kpi_performance')->group(function () {
            Route::get('/user/performance/summary', [ApiPerformanceController::class, 'summary']);
            Route::get('/user/performance/history', [ApiPerformanceController::class, 'history']);
            Route::get('/user/performance/daily', [ApiPerformanceController::class, 'daily']);
            Route::get('/user/performance/weekly', [ApiPerformanceController::class, 'weekly']);
            Route::get('/user/performance/daily-detail', [ApiPerformanceController::class, 'dailyDetail']);
            Route::get('/user/performance/review', [ApiPerformanceController::class, 'review']);
            Route::post('/user/performance/review/{id}/acknowledge', [ApiPerformanceController::class, 'acknowledgeReview']);
        });

        //shift plan
        Route::get('/user/shift/plan', [ShiftController::class, 'myShiftPlan'])->middleware('feature:fixed_shift,custom_shift');

        //Payslip plan
        Route::middleware('feature:payroll')->group(function () {
            Route::get('/payslips', [PayrollController::class, 'getMyPayslips'])
                ->name('employee.payslips');
            Route::get('/payslip/{id}/check', [PayrollController::class, 'checkPayslipAvailability'])
                ->name('employee.payslip.check');
            Route::get('/payslip/{id}/summary', [PayrollController::class, 'getPayslipSummary'])
                ->name('employee.payslip.summary');
            Route::get('/payslip/{id}/view', [PayrollController::class, 'viewPayslip'])
                ->name('employee.payslip.view');
            Route::get('/payslip/{id}/download', [PayrollController::class, 'downloadPayslip'])
                ->name('employee.payslip.download');
            Route::get('/payslip/{id}/base64', [PayrollController::class, 'getPayslipBase64'])
                ->name('employee.payslip.base64');
        });

        //team plan
        Route::get('/user/team-view', [UserController::class, 'team']);
        Route::get('/user/team-profile/{id}', [UserController::class, 'getUserProfile']);

        //Project
        Route::middleware('feature:project_management')->group(function () {
            Route::get('/user/get-project', [ProjectController::class, 'userProject']);
            Route::get('/manager/get-project', [ProjectController::class, 'index']);
            Route::get('/project-show/{id}', [ProjectController::class, 'show']);
        });

        //Task Assign
        Route::middleware('feature:task_single,task_group')->group(function () {
            Route::post('/manager/task-assign', [TaskController::class, 'store']);
            Route::get('/manager/task/view-assign-by-me', [TaskController::class, 'tasksAssignedByMe']);
            Route::get('/task/view-assign-to-me', [TaskController::class, 'tasksAssignedToMe']);
            Route::post('/task/update-status', [TaskController::class, 'TaskUpdate']);
            Route::post('/manager/update-approval', [TaskController::class, 'TaskApproval']);
            Route::get('/task/view-detail/{taskId}', [TaskController::class, 'getTaskDetails']);
        });

        Route::post('/user/profile-update', [UserController::class, 'update']);

        Route::get('/get-country', [UserController::class, 'getCountry']);
        Route::get('/get-states', [UserController::class, 'getStates']);
        Route::get('/get-cities', [UserController::class, 'getCities']);

        Route::get('/user/location/tracks', [UserController::class, 'getUserLocationTracks']);

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::get('/read/{id}', [NotificationController::class, 'markAsRead']);
            Route::get('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::get('/destroy/{id}', [NotificationController::class, 'destroy']);
            Route::get('/destroy-all', [NotificationController::class, 'destroyAllRead']);
        });

        //Travel
        Route::middleware('feature:wfh_travel')->group(function () {
            Route::get('/request/type', [RequestController::class, 'type']);
            Route::get('/request/view', [RequestController::class, 'view']);
            Route::post('/request/store', [RequestController::class, 'store']);
            Route::post('/request/update/{id}', [RequestController::class, 'update']);
            Route::post('/request/cancel/{id}', [RequestController::class, 'cancel']);
            Route::get('/manager/request/view', [RequestController::class, 'view_all'])->middleware('permission:requests,view');
            Route::post('/manager/request/update-status', [RequestController::class, 'updateStatus'])->middleware('permission:requests,approve');
            Route::get('/request/detail/{id}', [RequestController::class, 'show']);
        });
        
        Route::prefix('overtime')->middleware('feature:overtime')->group(function () {
            // Employee routes
            Route::get('/requests', [OvertimeController::class, 'index']);
            Route::get('/requests/detail/{id}', [OvertimeController::class, 'show']);
            Route::post('/requests/store', [OvertimeController::class, 'store']);
            Route::post('/requests/update/{id}', [OvertimeController::class, 'update']);
            Route::get('/requests/delete/{id}', [OvertimeController::class, 'destroy']);

            // Admin/Manager routes
            Route::get('/pending-approvals', [OvertimeController::class, 'pendingApprovals']);
            Route::post('/approve/{id}', [OvertimeController::class, 'approve']);
            Route::post('/reject/{id}', [OvertimeController::class, 'reject']);
        });
        
        Route::prefix('meetings')->middleware('feature:meetings')->group(function () {
            Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meetings,view');
            Route::get('/detail/{id}', [MeetingController::class, 'show'])->middleware('permission:meetings,view');
            Route::post('/store', [MeetingController::class, 'store'])->middleware('permission:meetings,create');
            Route::post('/update/{id}', [MeetingController::class, 'update'])->middleware('permission:meetings,edit');
            Route::post('/cancel/{id}', [MeetingController::class, 'cancel'])->middleware('permission:meetings,edit');
            Route::post('/complete/{id}', [MeetingController::class, 'complete'])->middleware('permission:meetings,edit');
            Route::post('/attendance/{id}', [MeetingController::class, 'updateAttendance'])->middleware('permission:meetings,view');
            Route::get('/mom-writer/meetings', [MeetingController::class, 'momWriterMeetings'])->middleware('permission:meetings,view');

        });
        
        Route::prefix('offboarding')->middleware('feature:offboarding')->group(function () {
            Route::get('/noticePeriode', [OffboardingController::class, 'noticePeriode']);
            Route::get('/my-requests', [OffboardingController::class, 'myRequests']);
            Route::get('/show', [OffboardingController::class, 'show']);
            Route::post('/store', [OffboardingController::class, 'store']);
            Route::post('/cancel/{id}', [OffboardingController::class, 'cancel']);
        });
        
        // AI assistant data feeds: each follows its own module key (my-profile / team are core).
        Route::prefix('ai')->group(function () {
            Route::get('/attendance-locations', [AIAttendanceLocationController::class, 'view_ai_all'])->middleware('feature:attendance');
            Route::get('/request', [AIRequestController::class, 'view_ai_all'])->middleware('feature:wfh_travel');
            Route::get('/attendance', [AIAttendanceController::class, 'view_ai_all'])->middleware('feature:attendance');
            Route::get('/my-profile', [AIProfileController::class, 'view_ai_all']);
            Route::get('/annuncement', [AIAnnoucementController::class, 'view_ai_all'])->middleware('feature:announcements');
            Route::get('/project', [AIProjectController::class, 'view_ai_all'])->middleware('feature:project_management');
            Route::get('/task', [AITaskController::class, 'view_ai_all'])->middleware('feature:task_single,task_group');
            Route::get('/expense', [AIExpenseController::class, 'view_ai_all'])->middleware('feature:expense_management');
            Route::get('/holiday', [AIHolidayController::class, 'view_ai_all'])->middleware('feature:holiday');
            Route::get('/shift-plan', [AIShiftController::class, 'view_ai_all'])->middleware('feature:fixed_shift,custom_shift');
            Route::get('/attendance-regularization', [AIAttendanceRegularizationController::class, 'view_ai_all'])->middleware('feature:regularization');
            Route::get('/team', [AITeamController::class, 'view_ai_all']);
            Route::get('/leave', [AILeaveController::class, 'view_ai_all'])->middleware('feature:leave_management');
            Route::get('/asset', [AIAssetController::class, 'view_ai_all'])->middleware('feature:asset_management');
            Route::get('/loan', [AILoanController::class, 'view_ai_all'])->middleware('feature:loan_management');
            Route::get('/performance', [AIPerformanceController::class, 'view_ai_all'])->middleware('feature:kpi_performance');
            Route::get('/policy', [AIPolicyController::class, 'view_ai_all']);
            Route::get('/offboarding', [AIOffboardingController::class, 'view_ai_all'])->middleware('feature:offboarding');
            Route::get('/meeting', [AIMeetingController::class, 'view_ai_all'])->middleware('feature:meetings');
            // daily_reports rows are the per-day work reports of a WFH / travel request (the
            // `daily_reports` plan feature is the Reports section, not these).
            Route::get('/daily-report', [AIDailyReportController::class, 'view_ai_all'])->middleware('feature:wfh_travel');
            Route::get('/onboarding', [AIOnboardingController::class, 'view_ai_all'])->middleware('feature:onboarding');
            Route::get('/recruitment', [AIRecruitmentController::class, 'view_ai_all'])->middleware('feature:recruitment');
            Route::get('/overtime', [AIOvertimeController::class, 'view_ai_all'])->middleware('feature:overtime');
            Route::get('/payroll', [AIPayrollController::class, 'view_ai_all'])->middleware('feature:payroll');
            Route::get('/leave-history', [AILeaveHistoryController::class, 'view_ai_all'])->middleware('feature:leave_management');
            // Each module inside is checked against its own feature + approve permission.
            Route::get('/approvals', [AIApprovalController::class, 'view_ai_all']);
        });
        
        Route::prefix('loan')->middleware('feature:loan_management')->group(function () {
            Route::get('/categories', [LoanController::class, 'fetch_category']);
            Route::get('/advance-limit', [LoanController::class, 'advanceLimit']);
            Route::post('/store', [LoanController::class, 'store']);
            Route::get('/show/{id}', [LoanController::class, 'show']);
            Route::get('/view', [LoanController::class, 'index']);
        });
    });
});



// Biometric-terminal integration: the SBXPC bridge posts punches to
// POST /api/v1/biometric/punches (key-authenticated, see routes/api_v1.php).
