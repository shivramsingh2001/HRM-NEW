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
use App\Http\Controllers\Api\Offboarding\offboardingController;

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

        //holiday
        Route::get('/holiday', [HolidayController::class, 'index']);
        Route::get('/fetch-all-user', [UserController::class, 'allusers']);

        //User Profile
        Route::get('/user/profile', [UserController::class, 'index']);

        //Leave
        Route::get('/leave-type', [LeaveController::class, 'fetch_type']);
        Route::get('/view-leave', [LeaveController::class, 'view']);
        Route::post('/apply-leave', [LeaveController::class, 'store']);

        Route::get('/view-team-leave', [LeaveController::class, 'view_all'])->middleware('permission:leave,view');
        Route::get('/update-team-leave-status/{id}', [LeaveController::class, 'updateLeaveStatus'])->middleware('permission:leave,approve');

        //Announcement
        Route::get('/view-announcement', [AnnouncementController::class, 'view']);
        Route::post('/create-announcement', [AnnouncementController::class, 'store']);
        Route::get('/all-announcement', [AnnouncementController::class, 'index']);
        Route::post('/acknowledge-announcement/{id}', [AnnouncementController::class, 'acknowledge']);

        //Expense
        Route::get('/expense-type', [ExpenseController::class, 'fetch_type']);
        Route::get('/view-expense', [ExpenseController::class, 'view']);
        Route::post('/create-expense', [ExpenseController::class, 'store']);

        Route::get('/view-team-expense', [ExpenseController::class, 'view_all']);
        Route::post('/team/expense-update', [ExpenseController::class, 'updateStatus']);

        //Attendance
        Route::post('/user/attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/user/attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/user/attendance/history', [AttendanceController::class, 'history']);
        Route::post('/user/attendance/track', [AttendanceController::class, 'trackLocation'])->middleware('throttle:location-ingest');
        Route::post('/user/attendance/track-batch', [AttendanceController::class, 'trackBatch'])->middleware('throttle:location-ingest');
        Route::get('/user/attendance/today', [AttendanceController::class, 'getAttendance']);
        Route::get('/user/attendance/today-locations', [AttendanceController::class, 'todayLocations']);
        
        Route::post('/location/status', [LocationTrackingController::class, 'locationStatus']);

        Route::post('/user/attendance/regularization', [AttendanceController::class, 'regularizationStore']);
        Route::get('/user/attendance/view-regularization', [AttendanceController::class, 'getMyRegularizations']);
        Route::get('/manager/attendance/view-regularization', [AttendanceController::class, 'getReporteesRegularizations']);
        Route::post('/manager/attendance/update-regularization-approval', [AttendanceController::class, 'regularizationApproval']);

        //shift plan
        Route::get('/user/shift/plan', [ShiftController::class, 'myShiftPlan']);

        //Payslip plan
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

        //team plan
        Route::get('/user/team-view', [UserController::class, 'team']);
        Route::get('/user/team-profile/{id}', [UserController::class, 'getUserProfile']);

        //Project 
        Route::get('/user/get-project', [ProjectController::class, 'userProject']);
        Route::get('/manager/get-project', [ProjectController::class, 'index']);
        Route::get('/project-show/{id}', [ProjectController::class, 'show']);

        //Task Assign
        Route::post('/manager/task-assign', [TaskController::class, 'store']);
        Route::get('/manager/task/view-assign-by-me', [TaskController::class, 'tasksAssignedByMe']);
        Route::get('/task/view-assign-to-me', [TaskController::class, 'tasksAssignedToMe']);
        Route::post('/task/update-status', [TaskController::class, 'TaskUpdate']);
        Route::post('/manager/update-approval', [TaskController::class, 'TaskApproval']);
        Route::get('/task/view-detail/{taskId}', [TaskController::class, 'getTaskDetails']);

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
        Route::get('/request/type', [RequestController::class, 'type']);
        Route::get('/request/view', [RequestController::class, 'view']);
        Route::post('/request/store', [RequestController::class, 'store']);
        Route::post('/request/update/{id}', [RequestController::class, 'update']);
        Route::post('/request/cancel/{id}', [RequestController::class, 'cancel']);
        Route::get('/manager/request/view', [RequestController::class, 'view_all'])->middleware('permission:requests,view');
        Route::post('/manager/request/update-status', [RequestController::class, 'updateStatus'])->middleware('permission:requests,approve');
        Route::get('/request/detail/{id}', [RequestController::class, 'show']);
        
        Route::prefix('overtime')->group(function () {
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
        
        Route::prefix('meetings')->group(function () {
            Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meetings,view');
            Route::get('/detail/{id}', [MeetingController::class, 'show'])->middleware('permission:meetings,view');
            Route::post('/store', [MeetingController::class, 'store'])->middleware('permission:meetings,create');
            Route::post('/update/{id}', [MeetingController::class, 'update'])->middleware('permission:meetings,edit');
            Route::post('/cancel/{id}', [MeetingController::class, 'cancel'])->middleware('permission:meetings,edit');
            Route::post('/complete/{id}', [MeetingController::class, 'complete'])->middleware('permission:meetings,edit');
            Route::post('/attendance/{id}', [MeetingController::class, 'updateAttendance'])->middleware('permission:meetings,view');
            Route::get('/mom-writer/meetings', [MeetingController::class, 'momWriterMeetings'])->middleware('permission:meetings,view');

        });
        
         Route::prefix('offboarding')->group(function () {
            Route::get('/noticePeriode', [OffboardingController::class, 'noticePeriode']);
            Route::get('/my-requests', [OffboardingController::class, 'myRequests']);
            Route::get('/show', [OffboardingController::class, 'show']);
            Route::post('/store', [offboardingController::class, 'store']);
        });
        
        Route::prefix('ai')->group(function () {
            Route::get('/attendance-locations', [AIAttendanceLocationController::class, 'view_ai_all']);
            Route::get('/request', [AIRequestController::class, 'view_ai_all']);
            Route::get('/attendance', [AIAttendanceController::class, 'view_ai_all']);
            Route::get('/my-profile', [AIProfileController::class, 'view_ai_all']);
            Route::get('/annuncement', [AIAnnoucementController::class, 'view_ai_all']);
            Route::get('/project', [AIProjectController::class, 'view_ai_all']);
            Route::get('/task', [AITaskController::class, 'view_ai_all']);
            Route::get('/expense', [AIExpenseController::class, 'view_ai_all']);
            Route::get('/holiday', [AIHolidayController::class, 'view_ai_all']);
            Route::get('/shift-plan', [AIShiftController::class, 'view_ai_all']);
            Route::get('/attendance-regularization', [AIAttendanceRegularizationController::class, 'view_ai_all']);
            Route::get('/team', [AITeamController::class, 'view_ai_all']);
            Route::get('/leave', [AILeaveController::class, 'view_ai_all']);
        });
        
        Route::prefix('loan')->group(function () {
            Route::get('/categories', [LoanController::class, 'fetch_category']);
            Route::post('/store', [LoanController::class, 'store']);
            Route::get('/show/{id}', [LoanController::class, 'show']);
            Route::get('/view', [LoanController::class, 'index']);
        });
    });
});



// Biometric-terminal integration: the SBXPC bridge posts punches to
// POST /api/v1/biometric/punches (key-authenticated, see routes/api_v1.php).
