<?php

use App\Http\Controllers\Announcement\AnnouncementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\Designation\DesignationController;
use App\Http\Controllers\Leave\LeaveController;
use App\Http\Controllers\Leave\LeaveTypeController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Loan\LoanCategoryController;
use App\Http\Controllers\Loan\LoanController;
use App\Http\Controllers\Payroll\MonthlyPayrollController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Holiday\HolidayController;
use App\Http\Controllers\Attendance\AttendanceContoller;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Shift\ShiftController;
use App\Http\Controllers\Expense\ExpenseController;
use App\Http\Controllers\Expense\ExpenseTypeController;
use App\Http\Controllers\Team\TeamController;
use App\Http\Controllers\Branch\BranchController;
use App\Http\Controllers\Expense\PaymentController;
use App\Http\Controllers\Payroll\PayrollMasterController;
use App\Http\Controllers\Payroll\UserPayrollController;
use App\Http\Controllers\Payroll\PayrollComponentController;
use App\Http\Controllers\Payroll\PayrollStructureController;
use App\Http\Controllers\Payroll\PayrollEmployeeStructureController;
use App\Http\Controllers\Payroll\StatutoryComplianceController;
use App\Http\Controllers\Payroll\PayrollBonusController;
use App\Http\Controllers\Payroll\PayrollArrearsController;
use App\Http\Controllers\Payroll\PayrollEngineSettingsController;
use App\Http\Controllers\Attendance\AttendanceRegularizationController;
use App\Http\Controllers\Attendance\RequestController;
use App\Http\Controllers\Leave\LeaveCreditController;
use App\Http\Controllers\Mom\MeetingController;
use App\Http\Controllers\Mom\MeetingMinuteController;
use App\Http\Controllers\Attendance\OvertimeController;
use App\Http\Controllers\Attendance\OvertimeSettingController;
use App\Http\Controllers\Performance\ManagerPerformanceReviewController;
use App\Http\Controllers\Performance\PerformanceController;
use App\Http\Controllers\offboarding\OffboardingController;
use App\Http\Controllers\Recruitment\JobOpeningController;
use App\Http\Controllers\Recruitment\RecruitmentController;
use App\Http\Controllers\Report\AttendanceReportController;
use App\Http\Controllers\Report\TaskReportController;
use App\Http\Controllers\Settings\AttendancePolicyController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;


Route::get('/optimize', function () {
    Artisan::call('optimize:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    return response()->json(['message' => 'Application optimized successfully!']);
});
Route::get('/test-raw', function () {
    return 'Raw test works!';
});

// Machine-to-machine: Super Admin Panel busts this app's feature cache.
Route::post('/internal/superadmin/feature-cache/bust',
    [\App\Http\Controllers\Internal\FeatureCacheController::class, 'bust']);

// routes/web.php
Route::get('/reset-password', function (\Illuminate\Http\Request $request) {
    // Get token and email from URL
    $token = $request->get('token');
    $email = $request->get('email');

    // Show reset password form
    return view('client.auth.reset-password', [
        'token' => $token,
        'email' => $email
    ]);
})->name('password.reset');

Route::group(['middleware' => ['tenant']], function () {
      Route::prefix('careers')->name('public.jobs.')->group(function () {
            Route::get('/', [RecruitmentController::class, 'index'])->name('list');
            Route::get('/{id}/apply', [RecruitmentController::class, 'showApplyForm'])->name('apply.form');
            Route::post('/apply-store', [RecruitmentController::class, 'submitApplication'])->name('apply');
        });
    
    Route::get('/', [AuthController::class, 'index'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.check')->middleware('throttle:login');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('forgot-password');

    // Super Admin Panel impersonation handoff (login mechanism — no auth guard).
    Route::get('/impersonate/consume', [\App\Http\Controllers\Impersonation\ImpersonationController::class, 'consume'])->name('impersonate.consume');

    Route::middleware(['auth'])->group(function () {

        Route::get('/test-fcm', function () {
            $firebase = app(\App\Services\FirebaseService::class);
            $result = $firebase->sendToDevice(
                'TEST_DEVICE_TOKEN', // You'll get this from Flutter later
                'Test Title',
                'Test Body'
            );
            return $result;
        });

        Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/impersonate/end', [\App\Http\Controllers\Impersonation\ImpersonationController::class, 'end'])->name('impersonate.end');
        Route::middleware(['auth', 'role:admin'])->group(function () {
            Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard.admin');
        });

        // HR Dashboard - Only hr can access
        Route::middleware(['auth', 'role:hr'])->group(function () {
            Route::get('/hr/dashboard', [DashboardController::class, 'hrDashboard'])->name('dashboard.hr');
        });

        // Manager Dashboard - Only manager can access
        Route::middleware(['auth', 'role:manager'])->group(function () {
            Route::get('/manager/dashboard', [DashboardController::class, 'managerDashboard'])->name('dashboard.manager');
        });

        // Employee Dashboard - Only employee can access
        Route::middleware(['auth', 'role:employee'])->group(function () {
            Route::get('/employee/dashboard', [DashboardController::class, 'employeeDashboard'])->name('dashboard.employee');
        });
        //Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        //State and City AJAX routes
        Route::get('/get-states', [LocationController::class, 'getStates'])->name('get.states');
        Route::get('/get-cities', [LocationController::class, 'getCities'])->name('get.cities');

        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceContoller::class, 'index'])->name('index');
            Route::get('/calendar-data', [AttendanceContoller::class, 'calendarData'])->name('calendar.data');
            Route::get('/attendance/sessions', [AttendanceContoller::class, 'showAttendanceSessions'])->name('sessions');
        });

        //Department Route
        Route::prefix('department')->name('department.')->group(function () {
            Route::get('/department', [DepartmentController::class, 'index'])->name('index');
            Route::post('/create', [DepartmentController::class, 'store'])->name('create');
            Route::post('/update/{id}', [DepartmentController::class, 'update'])->name('update');
            Route::get('/detail/{id}', [DepartmentController::class, 'detail'])->name('detail');
        });

        //Leave Type
        Route::prefix('leave-type')->name('leave-type.')->group(function () {
            Route::get('/', [LeaveTypeController::class, 'index'])->name('index');
            Route::post('/create', [LeaveTypeController::class, 'store'])->name('create');
            Route::post('/update/{id}', [LeaveTypeController::class, 'update'])->name('update');
            Route::get('/detail/{id}', [LeaveTypeController::class, 'detail'])->name('detail');
        });

        //Apply Leave
        Route::prefix('leave')->name('leave.')->group(function () {
            Route::get('/apply', [LeaveController::class, 'create'])->name('apply');
            Route::post('/apply-store', [LeaveController::class, 'store'])->name('apply-store');
            Route::get('/view', [LeaveController::class, 'index'])->name('view');
            Route::get('/update/{id}', [LeaveController::class, 'update'])->name('update');
            Route::post('/update-store/{id}', [LeaveController::class, 'update_store'])->name('update-store');
            Route::get('/delete/{id}', [LeaveController::class, 'delete'])->name('delete');

            Route::get('/view-all', [LeaveController::class, 'view_all'])->name('view-all')->middleware('permission:leave,view');
            Route::post('/update-status/{id}', [LeaveController::class, 'updateStatus'])->name('update-status')->middleware('permission:leave,approve');
        });

        //Designation Route
        Route::prefix('designation')->name('designation.')->group(function () {
            Route::get('/', [DesignationController::class, 'index'])->name('index');
            Route::post('/create', [DesignationController::class, 'store'])->name('create');
            Route::post('/update/{id}', [DesignationController::class, 'update'])->name('update');
            Route::get('/detail/{id}', [DesignationController::class, 'detail'])->name('detail');
        });

        //Employee Route
        Route::prefix('employee')->name('employee.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/create', [UserController::class, 'create'])->name('create');
            Route::post('/store', [UserController::class, 'store'])->name('store.step');
            Route::post('/save-step', [UserController::class, 'saveStep'])->name('save.step');
            Route::post('/complete-store', [UserController::class, 'completeStore'])->name('complete.store');
            Route::get('/edit/{id}', [UserController::class, 'edit'])->name('edit');
            Route::post('/update/{id}', [UserController::class, 'update'])->name('update-store');
            Route::get('/show/{id}', [UserController::class, 'show'])->name('show');
            Route::get('/load-data', [UserController::class, 'loadSavedData'])->name('load.data');

            Route::get('/export/excel', [UserController::class, 'exportExcel'])->name('export.excel');
            Route::get('/export/pdf', [UserController::class, 'exportPDF'])->name('export.pdf');
            Route::post('/update-step/{id}', [UserController::class, 'updateStep'])->name('update.step');
            Route::post('/update-complete/{id}', [UserController::class, 'completeUpdate'])->name('complete.update');

            Route::post('/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
            Route::post('/toggle-face-register', [UserController::class, 'toggleFaceRegister'])->name('toggle-face-register');
            Route::post('/update-attendance-type', [UserController::class, 'updateAttendanceType'])->name('update-attendance-type');
            Route::post('/toggle-location-tracking', [UserController::class, 'toggleLocationTracking'])->name('toggle-location-tracking');
            Route::post('/bulk-location-tracking', [UserController::class, 'bulkLocationTracking'])->name('bulk-location-tracking');
        });

        //Holiday Route
        Route::prefix('holidays')->name('holiday.')->group(function () {
            Route::get('/', [HolidayController::class, 'index'])->name('index');
            Route::post('/create', [HolidayController::class, 'store'])->name('create');
            Route::post('/update/{id}', [HolidayController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [HolidayController::class, 'status'])->name('status');
        });


        //Projects Route
        Route::prefix('projects')->name('project.')->group(function () {
            Route::get('/', [ProjectController::class, 'index'])->name('index');
            Route::post('/create', [ProjectController::class, 'store'])->name('create');
            Route::get('/view-details/{id}', [ProjectController::class, 'show'])->name('view-details');
            Route::post('/{id}/update', [ProjectController::class, 'update'])->name('update');
            Route::delete('/{id}', [ProjectController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/members', [ProjectController::class, 'getMembers'])->name('members');
        });

        //Tasks Route
        Route::prefix('tasks')->name('task.')->group(function () {
            Route::get('/assigned-by-me', [TaskController::class, 'tasksAssignedByMe'])->name('assigned-by-me');
            Route::get('/assigned-to-me', [TaskController::class, 'tasksAssignedToMe'])->name('assigned-to-me');
            Route::get('/create', [TaskController::class, 'create'])->name('create');
            Route::post('/store', [TaskController::class, 'store'])->name('store');
            Route::post('/delete/{id}', [TaskController::class, 'delete'])->name('delete');
            Route::get('/view-details/{id}', [TaskController::class, 'show'])->name('view-detail');
            Route::get('/{id}/edit', [TaskController::class, 'edit'])->name('edit');
            Route::post('/{id}/update', [TaskController::class, 'update'])->name('update');

            Route::post('/bulk-status', [TaskController::class, 'bulkStatusUpdate'])->name('bulk-status');
            Route::post('/update-status', [TaskController::class, 'TaskUpdate'])->name('update-status');
            Route::post('/approval-status', [TaskController::class, 'TaskApproval'])->name('approval-status')->middleware('permission:tasks,approve');

            Route::post('/{id}/comments', [TaskController::class, 'addComment'])->name('comments.store');
            Route::post('/comments/{id}/delete', [TaskController::class, 'deleteComment'])->name('comments.delete');
            Route::post('/{id}/attachments', [TaskController::class, 'uploadAttachment'])->name('attachments.store');
            Route::post('/attachments/{id}/delete', [TaskController::class, 'deleteAttachment'])->name('attachments.delete');
        });

       //Expense Type Route
        Route::prefix('expense-type')->name('expense-type.')->group(function () {
            Route::get('/', [ExpenseTypeController::class, 'index'])->name('index');
            Route::post('/create', [ExpenseTypeController::class, 'store'])->name('create');
            Route::post('/update/{id}', [ExpenseTypeController::class, 'update'])->name('update');
        });


        //Expense Route
        Route::prefix('expense')->name('expense.')->group(function () {
            Route::get('/', [ExpenseController::class, 'index'])->name('index');
            Route::delete('/delete/{id}', [ExpenseController::class, 'destroy'])->name('delete');
            Route::get('/view-all', [ExpenseController::class, 'view_all'])->name('view-all')->middleware('permission:expenses,view');
            Route::post('/create', [ExpenseController::class, 'store'])->name('create');
            Route::post('/update/{id}', [ExpenseController::class, 'update'])->name('update');
            Route::post('/update-status/{id}', [ExpenseController::class, 'updateStatus'])->name('update-status')->middleware('permission:expenses,approve');

             Route::get('/payment/{expenseId}', [ExpenseController::class, 'getPayments'])->name('payments');
        });

        Route::prefix('expense/payments')->name('expense.payments.')->group(function () {

            // Main payment listing page (single blade with modals)
            Route::get('/', [PaymentController::class, 'index'])->name('index');

            // AJAX endpoints for payment operations
            Route::post('/store', [PaymentController::class, 'store'])->name('store');
            Route::get('/show/{id}', [PaymentController::class, 'show'])->name('show'); // Get single payment for edit
            Route::put('/update/{id}', [PaymentController::class, 'update'])->name('update'); // Update payment
            Route::delete('/destroy/{id}', [PaymentController::class, 'destroy'])->name('destroy'); // Delete payment


            // View payments for a specific expense (alternative to main listing)
            Route::get('/{expenseId}', [PaymentController::class, 'expensePayments'])->name('by-expense');
            Route::get('/user-advances', [PaymentController::class, 'getUserPendingAdvances'])->name('user-advances');
            Route::get('/pending-advances', [PaymentController::class, 'getPendingAdvances'])->name('pending-advances');
            Route::get('/advance-summary/{expenseId}', [PaymentController::class, 'getAdvanceSummary'])->name('advance-summary');
        });
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/members', [TeamController::class, 'team'])->name('index');
            Route::get('/member-detail/{id}', [TeamController::class, 'viewUserProfile'])->name('member-detail');
            Route::get('/user/{id}/attendance/calendar', [TeamController::class, 'userAttendanceCalendarData'])->name('user.attendance.calendar');
            Route::get('/user/{id}/attendance/table', [TeamController::class, 'userAttendanceTableData'])->name('user.attendance.table');
            Route::get('/user/{id}/attendance-stats', [TeamController::class, 'userAttendanceStats'])->name('user.attendance.stats');
            Route::post('/get-user-shift', [TeamController::class, 'getUserShift'])->name('get-user-shift');

            Route::get('/attendance-summary', [TeamController::class, 'attendanceSummary'])->name('attendance-summary');
            Route::get('/attendance-summary/export', [TeamController::class, 'exportAttendanceSummary'])->name('attendance-summary.export');
            Route::get('/attendance-summary/quick', [TeamController::class, 'getQuickSummary'])->name('attendance-summary.quick');
             Route::post('/attendance-mark', [TeamController::class, 'markAttendance'])
                ->middleware('role:admin,hr,manager')
                ->name('mark-attendance');
            Route::get('/attendance-log', [TeamController::class, 'attendanceLog'])
                ->middleware('permission:team,view')
                ->name('attendance-log');
        });

        Route::prefix('branches')->name('branch.')->group(function () {
            Route::get('/', [BranchController::class, 'index'])->name('index');
            Route::post('/store', [BranchController::class, 'store'])->name('store');
            Route::put('/update/{id}', [BranchController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [BranchController::class, 'destroy'])->name('delete');
        });

        Route::middleware('shifts.custom')->prefix('shift')->name('shift.')->group(function () {
            // Read-only: roster + assignments list — admin / hr / manager
            Route::middleware('role:admin,hr,manager')->group(function () {
                Route::get('/roster', [ShiftController::class, 'roster'])->name('roster');
                Route::get('/user-shifts/data', [ShiftController::class, 'getUserShiftsData'])->name('user-shifts.data');
                Route::get('/user-shifts/export', [ShiftController::class, 'exportUserShifts'])->name('user-shifts.export');
                Route::get('/get-users-by-type', [ShiftController::class, 'getUsersByType'])->name('get-users-by-type');
            });

            // Everything that mutates shift data — admin / hr only
            Route::middleware('role:admin,hr')->group(function () {
                Route::get('/', [ShiftController::class, 'index'])->name('index');
                Route::post('/store', [ShiftController::class, 'store'])->name('store');
                Route::post('/update/{id}', [ShiftController::class, 'update'])->name('update');
                Route::post('/change-status/{id}', [ShiftController::class, 'changeStatus'])->name('change-status');
                Route::delete('/destroy/{id}', [ShiftController::class, 'destroy'])->name('destroy');

                Route::post('/assign', [ShiftController::class, 'assignShift'])->name('assign');
                Route::post('/update-user-shift', [ShiftController::class, 'updateUserShift'])->name('update-user-shift');
                Route::post('/bulk-delete-user-shifts', [ShiftController::class, 'bulkDeleteUserShifts'])->name('bulk-delete-user-shifts');
                Route::delete('/destroy-assigned/{id}', [ShiftController::class, 'destroyAssigned'])->name('destroy-assigned');
                Route::post('/user-shifts/bulk-update', [ShiftController::class, 'bulkUpdateUserShifts'])->name('user-shifts.bulk-update');
                Route::post('/user-shifts/assign-bulk', [ShiftController::class, 'assignBulkShifts'])->name('user-shifts.assign-bulk');
            });
        });
        Route::prefix('payroll-masters')->name('payroll-masters.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [PayrollMasterController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::get('/create', [PayrollMasterController::class, 'create'])->name('create')->middleware('permission:payroll,create');
            Route::post('/', [PayrollMasterController::class, 'store'])->name('store')->middleware('permission:payroll,create');
            Route::get('/{id}/edit', [PayrollMasterController::class, 'edit'])->name('edit')->middleware('permission:payroll,view');
            Route::put('/{id}', [PayrollMasterController::class, 'update'])->name('update')->middleware('permission:payroll,edit');
            Route::patch('/status/{id}', [PayrollMasterController::class, 'updateStatus'])->name('status')->middleware('permission:payroll,edit');
        });

        Route::prefix('my-payroll')->name('my-payroll.')->middleware(['feature:payroll', 'permission:payroll,view'])->group(function () {
            Route::get('/salary-slips', [MonthlyPayrollController::class, 'mySalarySlips'])->name('my-salary-slips');
            Route::get('/download/{id}', [MonthlyPayrollController::class, 'downloadSalarySlip'])->name('download-salary-slip');
            Route::get('/view/{id}', [MonthlyPayrollController::class, 'viewSalarySlip'])->name('view-salary-slip');
        });

        // Employee Payroll Routes
        Route::prefix('employee-payrolls')->name('employee-payrolls.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [UserPayrollController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::get('/create', [UserPayrollController::class, 'create'])->name('create')->middleware('permission:payroll,create');
            Route::post('/', [UserPayrollController::class, 'store'])->name('store')->middleware('permission:payroll,create');
            Route::get('/{id}/edit', [UserPayrollController::class, 'edit'])->name('edit')->middleware('permission:payroll,edit');
            Route::put('/{id}', [UserPayrollController::class, 'update'])->name('update')->middleware('permission:payroll,edit');
            Route::delete('/{id}', [UserPayrollController::class, 'destroy'])->name('destroy')->middleware('permission:payroll,delete');
        });

        // Monthly Payroll Routes
        Route::prefix('monthly-payrolls')->name('monthly-payrolls.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [MonthlyPayrollController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::get('/create', [MonthlyPayrollController::class, 'create'])->name('create')->middleware('permission:payroll,create');
            Route::post('/', [MonthlyPayrollController::class, 'store'])->name('store')->middleware('permission:payroll,create');
            Route::get('/{id}', [MonthlyPayrollController::class, 'show'])->name('show')->middleware('permission:payroll,view');
            Route::get('/{id}/edit', [MonthlyPayrollController::class, 'edit'])->name('edit')->middleware('permission:payroll,edit');
            Route::put('/{id}', [MonthlyPayrollController::class, 'update'])->name('update')->middleware('permission:payroll,edit');
            Route::get('/{id}/payslip', [MonthlyPayrollController::class, 'generatePayslip'])->name('payslip')->middleware('permission:payroll,view');
            Route::patch('/{id}/status', [MonthlyPayrollController::class, 'updateStatus'])->name('status')->middleware('permission:payroll,edit');
            Route::post('/{id}/reopen', [MonthlyPayrollController::class, 'reopen'])->name('reopen')->middleware('permission:payroll,manage');
            Route::post('/bulk-update', [MonthlyPayrollController::class, 'bulkUpdate'])->name('bulk-update')->middleware('permission:payroll,edit');
            Route::post('/export', [MonthlyPayrollController::class, 'export'])->name('export')->middleware('permission:payroll,export');
            Route::delete('/{id}', [MonthlyPayrollController::class, 'destroy'])->name('destroy')->middleware('permission:payroll,delete');
            Route::post('/calculate-estimates', [MonthlyPayrollController::class, 'calculateEstimates'])->name('calculate-estimates')->middleware('permission:payroll,create');
        });

        // ==================== Payroll rebuild — Phase 3 dynamic engine UI ====================
        // Additive: new screens alongside the existing payroll-masters/
        // employee-payrolls/monthly-payrolls routes above, which are untouched.
        Route::prefix('payroll-components')->name('payroll-components.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [PayrollComponentController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::post('/', [PayrollComponentController::class, 'store'])->name('store')->middleware('permission:payroll,manage');
            Route::put('/{id}', [PayrollComponentController::class, 'update'])->name('update')->middleware('permission:payroll,manage');
            Route::patch('/{id}/status', [PayrollComponentController::class, 'updateStatus'])->name('status')->middleware('permission:payroll,manage');
        });

        Route::prefix('payroll-structures')->name('payroll-structures.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [PayrollStructureController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::post('/', [PayrollStructureController::class, 'store'])->name('store')->middleware('permission:payroll,manage');
            Route::get('/{id}', [PayrollStructureController::class, 'show'])->name('show')->middleware('permission:payroll,view');
            Route::put('/{id}', [PayrollStructureController::class, 'update'])->name('update')->middleware('permission:payroll,manage');
            Route::delete('/{id}', [PayrollStructureController::class, 'destroy'])->name('destroy')->middleware('permission:payroll,manage');
        });

        Route::prefix('payroll-employee-structures')->name('payroll-employee-structures.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [PayrollEmployeeStructureController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::get('/for-user/{userId}', [PayrollEmployeeStructureController::class, 'forUser'])->name('for-user')->middleware('permission:payroll,view');
            Route::post('/', [PayrollEmployeeStructureController::class, 'store'])->name('store')->middleware('permission:payroll,edit');
            Route::get('/{id}', [PayrollEmployeeStructureController::class, 'show'])->name('show')->middleware('permission:payroll,view');
        });

        Route::prefix('payroll-compliance')->name('payroll-compliance.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [StatutoryComplianceController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::post('/rate/{type}', [StatutoryComplianceController::class, 'updateRate'])->name('update-rate')->middleware('permission:payroll,manage');
            Route::post('/pt-slabs', [StatutoryComplianceController::class, 'storePtSlab'])->name('pt-slabs.store')->middleware('permission:payroll,manage');
            Route::delete('/pt-slabs/{id}', [StatutoryComplianceController::class, 'destroyPtSlab'])->name('pt-slabs.destroy')->middleware('permission:payroll,manage');
        });

        Route::prefix('payroll-bonuses')->name('payroll-bonuses.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [PayrollBonusController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::post('/', [PayrollBonusController::class, 'store'])->name('store')->middleware('permission:payroll,create');
            Route::get('/{id}', [PayrollBonusController::class, 'show'])->name('show')->middleware('permission:payroll,view');
            Route::put('/{id}', [PayrollBonusController::class, 'update'])->name('update')->middleware('permission:payroll,edit');
            Route::post('/{id}/{action}', [PayrollBonusController::class, 'decide'])->name('decide')->where('action', 'approve|reject')->middleware('permission:payroll,approve');
        });

        Route::prefix('payroll-arrears')->name('payroll-arrears.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [PayrollArrearsController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::post('/{id}/cancel', [PayrollArrearsController::class, 'cancel'])->name('cancel')->middleware('permission:payroll,edit');
        });

        Route::prefix('payroll-engine-settings')->name('payroll-engine-settings.')->middleware('feature:payroll')->group(function () {
            Route::get('/', [PayrollEngineSettingsController::class, 'index'])->name('index')->middleware('permission:payroll,view');
            Route::post('/', [PayrollEngineSettingsController::class, 'update'])->name('update')->middleware('permission:payroll,manage');
        });

        Route::prefix('attendance-regularization')->name('attendance-regularization.')->group(function () {
            Route::get('/', [AttendanceRegularizationController::class, 'index'])->name('index');
            Route::post('/create', [AttendanceRegularizationController::class, 'store'])->name('store');
            Route::post('/update/{id}', [AttendanceRegularizationController::class, 'update'])->name('update');
            Route::DELETE('/destroy/{id}', [AttendanceRegularizationController::class, 'destroy'])->name('destroy');

            Route::get('/team', [AttendanceRegularizationController::class, 'manage'])
                ->name('manage')->middleware('permission:attendance,approve');
            Route::post('/team-approval', [AttendanceRegularizationController::class, 'regularizationApproval'])
                ->name('approval')->middleware('permission:attendance,approve');
            Route::get('/team-details/{id}', [AttendanceRegularizationController::class, 'getDetails'])
                ->name('details');
        });

        Route::prefix('requests')->name('requests.')->group(function () {
            Route::get('/', [RequestController::class, 'index'])->name('index');
            Route::get('/create', [RequestController::class, 'create'])->name('create');
            Route::post('/', [RequestController::class, 'store'])->name('store');
            Route::get('/{id}', [RequestController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [RequestController::class, 'edit'])->name('edit');
            Route::post('/{id}', [RequestController::class, 'update'])->name('update');
            Route::post('/{id}/destroy', [RequestController::class, 'destroy'])->name('destroy');
            Route::delete('/attachments/{attachmentId}', [RequestController::class, 'deleteAttachment'])
                ->name('delete-attachment');
            Route::get('/attachments/{attachmentId}/download', [RequestController::class, 'downloadAttachment'])
                ->name('download-attachment');
            Route::get('/stats', [RequestController::class, 'getStats'])->name('stats');
            Route::get('/export', [RequestController::class, 'export'])->name('export');
        });

        Route::get('/manager/requests', [RequestController::class, 'managerIndex'])->name('manager.requests');
        Route::get('/manager/requests/{id}', [RequestController::class, 'managerShow'])->name('manager.requests.show');
        Route::post('/manager/requests/{id}/approve', [RequestController::class, 'approve']);
        Route::post('/manager/requests/{id}/reject', [RequestController::class, 'reject']);
        Route::post('/manager/requests/bulk-approve', [RequestController::class, 'bulkApprove']);
        Route::get('/manager/requests/export', [RequestController::class, 'managerExport']);
        Route::get('/manager/stats', [RequestController::class, 'managerStats']);

        Route::prefix('announcement')->name('announcement.')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])->name('index');
            Route::get('/view-all', [AnnouncementController::class, 'allAnnouncements'])->name('all');
            Route::get('/{id}', [AnnouncementController::class, 'show'])->name('show');
            Route::post('/acknowledge/{id}', [AnnouncementController::class, 'acknowledge'])->name('acknowledge');

            Route::post('/store', [AnnouncementController::class, 'store'])->name('store')->middleware('permission:announcements,create');
            Route::put('/update/{id}', [AnnouncementController::class, 'update'])->name('update')->middleware('permission:announcements,edit');
            Route::delete('/delete/{id}', [AnnouncementController::class, 'destroy'])->name('destroy')->middleware('permission:announcements,delete');
            Route::post('/toggle-status/{id}', [AnnouncementController::class, 'toggleStatus'])->name('toggle-status')->middleware('permission:announcements,edit');
            Route::get('/{id}/acknowledgments', [AnnouncementController::class, 'acknowledgments'])->name('acknowledgments')->middleware('permission:announcements,view');
        });

        Route::prefix('leave-credit')->name('leave-credit.')->group(function () {
            Route::get('/', [LeaveCreditController::class, 'index'])->name('index');
            Route::post('/process', [LeaveCreditController::class, 'creditLeaves'])->name('process');
            Route::get('/manual/create', [LeaveCreditController::class, 'createManual'])->name('manual.create');
            Route::post('/manual', [LeaveCreditController::class, 'manualCredit'])->name('manual.store')->middleware('permission:leave,manage');
            Route::post('/manual-debit', [LeaveCreditController::class, 'manualDebit'])->name('manual.debit')->middleware('permission:leave,manage');
            Route::post('/new-joiner/{user_id}', [LeaveCreditController::class, 'handleNewJoiner'])->name('new-joiner');
            Route::get('/reports', [LeaveCreditController::class, 'reports'])->name('reports');
            Route::get('/transactions/{user_id}', [LeaveCreditController::class, 'userTransactions'])->name('transactions');
            Route::get('/my-transactions/', [LeaveCreditController::class, 'myTransactions'])->name('my-transactions');
        });
        
        Route::prefix('meetings')->name('meetings.')->group(function () {
            Route::get('/', [MeetingController::class, 'index'])->name('index')->middleware('permission:meetings,view');
            Route::get('/create', [MeetingController::class, 'create'])->name('create')->middleware('permission:meetings,create');
            Route::post('/store', [MeetingController::class, 'store'])->name('store')->middleware('permission:meetings,create');
            Route::get('/show/{id}', [MeetingController::class, 'show'])->name('show')->middleware('permission:meetings,view');
            Route::get('/{id}/edit', [MeetingController::class, 'edit'])->name('edit')->middleware('permission:meetings,edit');
            Route::put('/{id}', [MeetingController::class, 'update'])->name('update')->middleware('permission:meetings,edit');
            Route::delete('/{id}', [MeetingController::class, 'destroy'])->name('destroy')->middleware('permission:meetings,delete');
            Route::post('/{id}/cancel', [MeetingController::class, 'cancel'])->name('cancel')->middleware('permission:meetings,edit');
            Route::post('/{id}/reschedule', [MeetingController::class, 'reschedule'])->name('reschedule')->middleware('permission:meetings,edit');
            Route::post('/{id}/attendance', [MeetingController::class, 'markAttendance'])->name('attendance')->middleware('permission:meetings,edit');
        });

        Route::prefix('meetings/mom')->name('meetings.mom.')->group(function () {
            Route::get('/create/{id}', [MeetingMinuteController::class, 'create'])->name('create')->middleware('permission:meetings,edit');
            Route::post('/store', [MeetingMinuteController::class, 'store'])->name('store')->middleware('permission:meetings,edit');
            Route::post('/{id}/reopen', [MeetingMinuteController::class, 'reopen'])->name('reopen')->middleware('permission:meetings,edit');
        });

        // Overtime Routes
        Route::prefix('overtime')->group(function () {
            // Employee Routes
            Route::get('/requests', [OvertimeController::class, 'index'])->name('overtime.index');
            Route::post('/store', [OvertimeController::class, 'store'])->name('overtime.store');
            Route::put('/update/{id}', [OvertimeController::class, 'update'])->name('overtime.update');
            Route::delete('/destroy/{id}', [OvertimeController::class, 'destroy'])->name('overtime.destroy');

            // Approver Routes
            Route::middleware('permission:overtime,view')->group(function () {
                Route::get('/view-all', [OvertimeController::class, 'viewAll'])->name('overtime.view-all');
                Route::get('/show/{id}', [OvertimeController::class, 'show'])->name('overtime.show');
            });
            Route::middleware('permission:overtime,approve')->group(function () {
                Route::post('/approve/{id}', [OvertimeController::class, 'approve'])->name('overtime.approve');
                Route::post('/reject/{id}', [OvertimeController::class, 'reject'])->name('overtime.reject');
            });

            // Tenant overtime policy
            Route::middleware('permission:overtime,manage')->group(function () {
                Route::get('/settings', [OvertimeSettingController::class, 'index'])->name('overtime.settings');
                Route::put('/settings/update', [OvertimeSettingController::class, 'update'])->name('overtime.settings.update');
                Route::post('/settings/reset', [OvertimeSettingController::class, 'reset'])->name('overtime.settings.reset');
            });
        });

        // Tenant attendance policy (per-tenant monthly late allowance)
        Route::middleware('role:admin,hr')->prefix('attendance-policy')->name('attendance-policy.')->group(function () {
            Route::get('/', [AttendancePolicyController::class, 'index'])->name('index');
            Route::put('/', [AttendancePolicyController::class, 'update'])->name('update');
        });

        // Tenant shift settings (custom-shifts toggle + fixed company shift)
        Route::middleware('role:admin,hr')->prefix('shift-settings')->name('shift-settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Settings\ShiftSettingsController::class, 'index'])->name('index');
            Route::put('/', [\App\Http\Controllers\Settings\ShiftSettingsController::class, 'update'])->name('update');
        });

        // Field GPS tracking add-on. The settings page is always reachable (it
        // explains the add-on when off); saving changes requires it enabled.
        Route::middleware('role:admin,hr')->prefix('settings/field-tracking')->name('settings.field-tracking.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Settings\FieldTrackingController::class, 'index'])->name('index');
            Route::put('/', [\App\Http\Controllers\Settings\FieldTrackingController::class, 'update'])->middleware('field.tracking')->name('update');
        });

        // Tier 2 — approval workflows, API clients, webhooks
        Route::middleware('role:admin,hr')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/approvals', [\App\Http\Controllers\Settings\ApprovalWorkflowController::class, 'index'])->name('approvals.index');
            Route::post('/approvals/workflows', [\App\Http\Controllers\Settings\ApprovalWorkflowController::class, 'storeWorkflow'])->name('approvals.workflows.store');
            Route::delete('/approvals/workflows/{id}', [\App\Http\Controllers\Settings\ApprovalWorkflowController::class, 'destroyWorkflow'])->name('approvals.workflows.destroy');
            Route::post('/approvals/delegations', [\App\Http\Controllers\Settings\ApprovalWorkflowController::class, 'storeDelegation'])->name('approvals.delegations.store');
            Route::delete('/approvals/delegations/{id}', [\App\Http\Controllers\Settings\ApprovalWorkflowController::class, 'destroyDelegation'])->name('approvals.delegations.destroy');

            Route::get('/api-clients', [\App\Http\Controllers\Settings\ApiClientController::class, 'index'])->name('api.index');
            Route::post('/api-clients', [\App\Http\Controllers\Settings\ApiClientController::class, 'store'])->name('api.store');
            Route::post('/api-clients/{id}/rotate', [\App\Http\Controllers\Settings\ApiClientController::class, 'rotate'])->name('api.rotate');
            Route::delete('/api-clients/{id}', [\App\Http\Controllers\Settings\ApiClientController::class, 'destroy'])->name('api.destroy');

            Route::get('/webhooks', [\App\Http\Controllers\Settings\WebhookController::class, 'index'])->name('webhooks.index');
            Route::post('/webhooks', [\App\Http\Controllers\Settings\WebhookController::class, 'store'])->name('webhooks.store');
            Route::post('/webhooks/{id}/test', [\App\Http\Controllers\Settings\WebhookController::class, 'test'])->name('webhooks.test');
            Route::delete('/webhooks/{id}', [\App\Http\Controllers\Settings\WebhookController::class, 'destroy'])->name('webhooks.destroy');

            // Biometric terminals (SBXPC bridge)
            Route::get('/biometric', [\App\Http\Controllers\Settings\BiometricController::class, 'index'])->name('biometric.index');
            Route::post('/biometric/devices', [\App\Http\Controllers\Settings\BiometricController::class, 'storeDevice'])->name('biometric.devices.store');
            Route::put('/biometric/devices/{device}', [\App\Http\Controllers\Settings\BiometricController::class, 'updateDevice'])->name('biometric.devices.update');
            Route::delete('/biometric/devices/{device}', [\App\Http\Controllers\Settings\BiometricController::class, 'destroyDevice'])->name('biometric.devices.destroy');
            Route::post('/biometric/devices/{device}/bridge-key', [\App\Http\Controllers\Settings\BiometricController::class, 'generateBridgeKey'])->name('biometric.devices.bridge-key');
            Route::get('/biometric/devices/{device}/config', [\App\Http\Controllers\Settings\BiometricController::class, 'downloadConfig'])->name('biometric.devices.config');
            Route::get('/biometric/devices/{device}/enrollments', [\App\Http\Controllers\Settings\BiometricController::class, 'enrollments'])->name('biometric.enrollments');
            Route::post('/biometric/devices/{device}/enrollments', [\App\Http\Controllers\Settings\BiometricController::class, 'mapEnrollment'])->name('biometric.enrollments.map');
            Route::post('/biometric/devices/{device}/enrollments/auto', [\App\Http\Controllers\Settings\BiometricController::class, 'autoMap'])->name('biometric.enrollments.auto');
            Route::post('/biometric/devices/{device}/roster/sync', [\App\Http\Controllers\Settings\BiometricController::class, 'syncRoster'])->name('biometric.roster.sync');
            Route::post('/biometric/enrollments/{enrollment}/card', [\App\Http\Controllers\Settings\BiometricController::class, 'setCard'])->name('biometric.enrollments.card');
            Route::post('/biometric/enrollments/{enrollment}/repush', [\App\Http\Controllers\Settings\BiometricController::class, 'repushEnrollment'])->name('biometric.enrollments.repush');
            Route::post('/biometric/enrollments/{enrollment}/remove', [\App\Http\Controllers\Settings\BiometricController::class, 'removeEnrollment'])->name('biometric.enrollments.remove');
            Route::delete('/biometric/enrollments/{enrollment}', [\App\Http\Controllers\Settings\BiometricController::class, 'unmapEnrollment'])->name('biometric.enrollments.unmap');
            Route::get('/biometric/punches', [\App\Http\Controllers\Settings\BiometricController::class, 'punches'])->name('biometric.punches');
            Route::post('/biometric/punches/{punch}/reprocess', [\App\Http\Controllers\Settings\BiometricController::class, 'reprocessPunch'])->name('biometric.punches.reprocess');
        });

        // Tier 2 / T2-F — attendance analytics dashboard + anomaly review
        Route::middleware('role:admin,hr,manager')->prefix('analytics')->name('analytics.attendance.')->group(function () {
            Route::get('/attendance', [\App\Http\Controllers\Analytics\AttendanceAnalyticsController::class, 'index'])->name('index');
            Route::post('/attendance/anomaly/{id}', [\App\Http\Controllers\Analytics\AttendanceAnalyticsController::class, 'reviewAnomaly'])->name('anomaly.review');
        });
        
        Route::prefix('loan')->group(function () {

            // Loan Categories Routes
            Route::prefix('categories')->name('loan.categories.')->group(function () {
                Route::get('/', [LoanCategoryController::class, 'index'])->name('index');
                Route::post('/', [LoanCategoryController::class, 'store'])->name('store');
                Route::get('/{id}', [LoanCategoryController::class, 'show'])->name('show');
                Route::put('/{id}', [LoanCategoryController::class, 'update'])->name('update');
                Route::delete('/{id}', [LoanCategoryController::class, 'destroy'])->name('destroy');
                Route::get('/active/list', [LoanCategoryController::class, 'getActiveCategories'])->name('active');
                Route::post('/{id}/calculate-emi', [LoanCategoryController::class, 'calculateEmi'])->name('calculate-emi');
            });
    
            // Employee Loan Requests Routes
            Route::prefix('requests')->name('loan.requests.')->group(function () {
                Route::get('/', [LoanController::class, 'index'])->name('index');
                Route::post('/', [LoanController::class, 'store'])->name('store');
                Route::get('/{id}', [LoanController::class, 'show'])->name('show');
                Route::put('/{id}', [LoanController::class, 'update'])->name('update');
                Route::delete('/{id}', [LoanController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/cancel', [LoanController::class, 'cancel'])->name('cancel');
    
                Route::post('/lumpsum-payment/{id}', [LoanController::class, 'processLumpsumPayment'])->name('loan.lumpsum.payment');
    
                // Additional routes
                Route::get('/my-loans/summary', [LoanController::class, 'myLoans'])->name('my-loans');
                Route::get('/schedule/{id}', [LoanController::class, 'getRepaymentSchedule'])->name('schedule');
            });
    
            // Admin/Manager Loan Approval Routes
            Route::prefix('approvals')->name('loan.approvals.')->middleware(['auth', 'role:admin,hr'])->group(function () {
                Route::get('/', [LoanController::class, 'view_all'])->name('pending');
                Route::post('/{id}/approve', [LoanController::class, 'approve'])->name('approve');
                Route::post('/{id}/reject', [LoanController::class, 'reject'])->name('reject');
                Route::post('/{id}/disburse', [LoanController::class, 'disburse'])->name('disburse');
            });
    
            // Reports Routes
            Route::prefix('reports')->name('loan.reports.')->middleware(['auth', 'role:admin,hr,manager'])->group(function () {
                Route::get('/summary', [LoanController::class, 'getStats'])->name('summary');
                Route::get('/employee/{userId}', [LoanController::class, 'getEmployeeSummary'])->name('employee');
                Route::get('/export', [LoanController::class, 'exportReport'])->name('export');
            });
        });
        Route::prefix('offboarding')->name('offboarding.')->group(function () {
            // Main routes (static paths first)
            Route::get('/create', [OffboardingController::class, 'create'])->name('create');
            Route::get('/manager', [OffboardingController::class, 'managerIndex'])->name('manager');
            Route::get('/index', [OffboardingController::class, 'adminIndex'])->name('index');
            Route::get('/employee/index', [OffboardingController::class, 'employeeIndex'])->name('employee');
    
            Route::post('/store', [OffboardingController::class, 'store'])->name('store');
    
            // Parameterized routes (dynamic paths last)
            Route::get('/{id}', [OffboardingController::class, 'show'])->name('show');
    
            // Approval routes
            Route::post('/{id}/manager-review', [OffboardingController::class, 'managerReview'])->name('manager-review');
            Route::post('/{id}/hr-review', [OffboardingController::class, 'hrReview'])->name('hr-review');
    
            // Knowledge Transfer routes
            Route::post('/{id}/knowledge-transfer/start', [OffboardingController::class, 'startKnowledgeTransfer'])->name('knowledge-transfer.start');
            Route::post('/{id}/knowledge-transfer/complete', [OffboardingController::class, 'completeKnowledgeTransfer'])->name('knowledge-transfer.complete');
    
            // Clearance routes
            Route::post('/{id}/asset-clearance/update', [OffboardingController::class, 'updateAssetClearance'])->name('asset-clearance.update');
    
            // Exit Interview routes
            Route::post('/{id}/exit-interview', [OffboardingController::class, 'storeExitInterview'])->name('exit-interview.store');
            Route::put('/{id}/exit-interview/{exitId}', [OffboardingController::class, 'updateExitInterview'])->name('exit-interview.update');
    
            // Settlement routes
            Route::post('/{id}/final-settlement/process', [OffboardingController::class, 'processFinalSettlement'])->name('final-settlement.process');
            Route::post('/{id}/final-settlement/paid', [OffboardingController::class, 'markSettlementPaid'])->name('final-settlement.paid');
    
            // Status change routes
            Route::post('/{id}/complete', [OffboardingController::class, 'complete'])->name('complete');
            Route::post('/{id}/reject', [OffboardingController::class, 'reject'])->name('reject');
            Route::post('/{id}/cancel', [OffboardingController::class, 'cancel'])->name('cancel');
    
            // Remarks route
            Route::post('/{id}/remarks', [OffboardingController::class, 'updateRemarks'])->name('remarks.update');
        });
        
        Route::prefix('performance')->name('performance.')->group(function () {
            Route::get('/my-dashboard', [PerformanceController::class, 'myPerformance'])->name('my-dashboard');
            Route::get('/employee/{userId}', [PerformanceController::class, 'individualReport'])->name('individual');   
            Route::get('/team', [PerformanceController::class, 'teamReport'])->name('team');
            Route::get('/team/export', [PerformanceController::class, 'employeeSearch'])->name('team.export');     
        });
        
        Route::prefix('job-openings')->name('job-openings.')->middleware('feature:recruitment')->group(function () {
            Route::get('/', [JobOpeningController::class, 'index'])->name('index');
            Route::get('/create', [JobOpeningController::class, 'create'])->name('create');
            Route::post('/store', [JobOpeningController::class, 'store'])->name('store');
            Route::get('/show/{id}', [JobOpeningController::class, 'show'])->name('show');
            Route::get('/edit/{id}', [JobOpeningController::class, 'edit'])->name('edit');
            Route::post('/update/{id}', [JobOpeningController::class, 'update'])->name('update');
            Route::get('/destroy/{id}', [JobOpeningController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/publish', [JobOpeningController::class, 'publish'])->name('publish');
            Route::post('/{id}/close', [JobOpeningController::class, 'close'])->name('close');
            Route::post('/{id}/duplicate', [JobOpeningController::class, 'duplicate'])->name('duplicate');
            Route::get('/{id}/applications', [JobOpeningController::class, 'getApplications'])->name('applications');
        });
        Route::prefix('recruitment')->name('recruitment.')->middleware('feature:recruitment')->group(function () {
            Route::post('/applications/{id}/shortlist', [JobOpeningController::class, 'shortlist'])->name('shortlist');
            Route::post('/applications/{id}/reject', [JobOpeningController::class, 'reject'])->name('reject');
            Route::post('/{id}/schedule-interview', [JobOpeningController::class, 'scheduleInterview'])->name('schedule-interview');
            Route::post('/interviews/{id}/feedback', [JobOpeningController::class, 'submitFeedback'])->name('submit-feedback');
            Route::post('/applications/{id}/release-offer', [JobOpeningController::class, 'releaseOffer'])->name('release-offer');
            Route::post('/applications/{id}/offer-accepted', [JobOpeningController::class, 'offerAccepted'])
                ->name('recruitment.offer-accepted');
            Route::post('/applications/{id}/offer-rejected', [JobOpeningController::class, 'offerRejected'])
                ->name('recruitment.offer-rejected');
            Route::post('/applications/{id}/next-round', [JobOpeningController::class, 'nextRound'])->name('recruitment.next-round');
            Route::post('/applications/{id}/hire', [JobOpeningController::class, 'hire'])->name('recruitment.hire');
    
            Route::get('/interviewers', [JobOpeningController::class, 'getInterviewers'])->name('interviewers');
            Route::get('/stages', [JobOpeningController::class, 'getRecruitmentStages'])->name('stages');
            Route::get('/applications/{id}/interview-details', [JobOpeningController::class, 'interviewDetails'])->name('interview-details');
        });

      
        Route::prefix('/performance/reviews')->name('performance.reviews.')->group(function () {
            Route::get('/', [ManagerPerformanceReviewController::class, 'index'])->name('index');
            Route::post('/store/{userId}', [ManagerPerformanceReviewController::class, 'store'])->name('store');
            Route::get('/show/{id}', [ManagerPerformanceReviewController::class, 'show'])->name('show');
            Route::put('/update/{id}', [ManagerPerformanceReviewController::class, 'update'])->name('update');
            Route::delete('/destroy/{id}', [ManagerPerformanceReviewController::class, 'destroy'])->name('destroy');
            Route::post('/acknowledge/{id}', [ManagerPerformanceReviewController::class, 'acknowledge'])->name('acknowledge');
            Route::get('/pending-count', [ManagerPerformanceReviewController::class, 'pendingCount'])->name('pending-count');
            Route::get('/export', [ManagerPerformanceReviewController::class, 'export'])->name('export');
        });
        
        Route::prefix('report')->name('report.')->middleware('role:admin,hr,manager')->group(function () {
            Route::get('/attendance', [AttendanceReportController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/detail', [AttendanceReportController::class, 'detailAttendanceReport'])->name('attendance.detail.index');
            Route::get('/attendance/detail/export', [AttendanceReportController::class, 'detailExportAttendance'])->name('attendance.detail.export');
            Route::get('/attendance/day', [AttendanceReportController::class, 'dayAttendanceReport'])->name('attendance.day.index');
            Route::get('/attendance/day/export', [AttendanceReportController::class, 'dayExportAttendance'])->name('attendance.day.export');
            Route::get('/attendance/hourly', [AttendanceReportController::class, 'hourlyAttendanceReport'])->name('attendance.hourly.index');
            Route::get('/attendance/hourly/export', [AttendanceReportController::class, 'hourlyExportAttendance'])->name('attendance.hourly.export');
            Route::get('/attendance/overall', [AttendanceReportController::class, 'overallAttendanceReport'])->name('attendance.overall.index');
            Route::get('/attendance/overall/export', [AttendanceReportController::class, 'overallExportAttendance'])->name('attendance.overall.export');
            Route::get('/attendance/employee-wise', [AttendanceReportController::class, 'employeeWiseAttendance'])->name('attendance.detailed.index');
            Route::get('/attendance/employee-wise/export', [AttendanceReportController::class, 'employeeWisExportReport'])->name('attendance.detailed.export');
            Route::get('/attendance/monthly/summary', [AttendanceReportController::class, 'employeeWiseAttendance'])->name('attendance.monthly.summary.index');
            Route::get('/attendance/monthly/summary/export', [AttendanceReportController::class, 'employeeWisExportReport'])->name('attendance.monthly.summary.export');
            
            Route::get('/attendance/branch-wise', [AttendanceReportController::class, 'branchWiseAttendanceReport'])->name('attendance.branch-wise');
            Route::get('/attendance/branch-wise/{branchId}', [AttendanceReportController::class, 'branchWiseDetailReport'])->name('attendance.branch-wise.detail');
            Route::get('/attendance/branch-wise/{branchId}/export', [AttendanceReportController::class, 'branchWiseDetailExport'])->name('attendance.branch-wise.detail.export');

            Route::get('/overtime/monthly', [AttendanceReportController::class, 'overtimeMonthlyReport'])->name('overtime.monthly.index');

            Route::get('/task-project', [TaskReportController::class, 'index'])->name('task-project.index');
        });

    });
});
