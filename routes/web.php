<?php

use App\Http\Controllers\Announcement\AnnouncementController;
use App\Http\Controllers\Broadcast\BroadcastController;
use App\Http\Controllers\Broadcast\BroadcastNotificationCenterController;
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
use App\Http\Controllers\Asset\AssetController;
use App\Http\Controllers\Asset\AssetLifecycleController;
use App\Http\Controllers\Asset\AssetCategoryController;
use App\Http\Controllers\Asset\AssetTypeController;
use App\Http\Controllers\Asset\VendorController;
use App\Http\Controllers\Asset\AssetAttachmentController;
use App\Http\Controllers\Report\AssetReportController;
use App\Http\Controllers\Report\ShiftReportController;
use App\Http\Controllers\Report\PayrollReportController;
use App\Http\Controllers\Report\LeaveReportController;
use App\Http\Controllers\Shift\ShiftController;
use App\Http\Controllers\Expense\ExpenseController;
use App\Http\Controllers\Expense\ExpenseTypeController;
use App\Http\Controllers\Team\TeamController;
use App\Http\Controllers\Branch\BranchController;
use App\Http\Controllers\AttendanceLocation\AttendanceLocationController;
use App\Http\Controllers\Expense\PaymentController;
use App\Http\Controllers\Expense\PaymentBatchController;
use App\Http\Controllers\Expense\ExpenseBudgetController;
use App\Http\Controllers\Expense\ExpenseReportController;
use App\Http\Controllers\Expense\ExpensePayrollRouteController;
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
use App\Http\Controllers\Recruitment\OnboardingController;
use App\Http\Controllers\Report\AttendanceReportController;
use App\Http\Controllers\Report\TaskReportController;
use App\Http\Controllers\Report\ProjectReportController;
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

// Expense receipt download. Deliberately outside the session/tenant groups: the
// `signed` middleware is the gate (a short-lived, tamper-proof URL minted by
// ExpenseAttachmentService::url() only for records the caller can already see),
// which lets the mobile app load receipts without a web session.
Route::get('/expense/file/{id}', [ExpenseController::class, 'file'])
    ->middleware('signed')
    ->whereNumber('id')
    ->name('expense.file');

// Same model for an EXTRA receipt (expense_attachments): a signed, tenant-carrying, short-lived URL.
Route::get('/expense/attachment/{id}', [ExpenseController::class, 'attachment'])
    ->middleware('signed')
    ->whereNumber('id')
    ->name('expense.attachment');

// Stored-file safety net (config/file_storage.php): an /uploads/... link whose file is no
// longer on local disk (moved to Google Cloud Storage) redirects a logged-in user to a
// short-lived signed URL. Files still in public/uploads are served by the web server first.
Route::get('/uploads/{path}', [\App\Http\Controllers\FileController::class, 'uploads'])
    ->where('path', '.*')
    ->middleware('auth')
    ->name('files.uploads');

Route::group(['middleware' => ['tenant']], function () {
      // {company} = tenant code (tenants.subdomain); resolved + removed by TenantMiddleware
      Route::prefix('careers/{company}')->name('public.jobs.')->middleware('feature:recruitment')->group(function () {
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

        Route::prefix('attendance')->name('attendance.')->middleware('feature:attendance')->group(function () {
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
        Route::prefix('leave-type')->name('leave-type.')->middleware('feature:leave_management')->group(function () {
            Route::get('/', [LeaveTypeController::class, 'index'])->name('index');
            Route::post('/create', [LeaveTypeController::class, 'store'])->name('create');
            Route::post('/update/{id}', [LeaveTypeController::class, 'update'])->name('update');
            Route::get('/detail/{id}', [LeaveTypeController::class, 'detail'])->name('detail');
        });

        //Apply Leave
        Route::prefix('leave')->name('leave.')->middleware('feature:leave_management')->group(function () {
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
            Route::post('/import', [UserController::class, 'importEmployees'])->name('import')->middleware('role:admin,hr');
            Route::get('/import/template', [UserController::class, 'importTemplate'])->name('import.template')->middleware('role:admin,hr');
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
            Route::post('/bulk-push-to-device', [UserController::class, 'bulkPushToDevice'])->name('bulk-push-to-device')->middleware('feature:attendance_biometric');
        });

        //Holiday Route
        Route::prefix('holidays')->name('holiday.')->middleware('feature:holiday')->group(function () {
            Route::get('/', [HolidayController::class, 'index'])->name('index');
            Route::post('/create', [HolidayController::class, 'store'])->name('create');
            Route::post('/update/{id}', [HolidayController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [HolidayController::class, 'status'])->name('status');
        });


        //Projects Route
        Route::prefix('projects')->name('project.')->middleware('feature:project_management')->group(function () {
            Route::get('/', [ProjectController::class, 'index'])->name('index')->middleware('permission:projects,view');
            Route::post('/create', [ProjectController::class, 'store'])->name('create')->middleware('permission:projects,create');
            Route::get('/view-details/{id}', [ProjectController::class, 'show'])->name('view-details')->middleware('permission:projects,view');
            Route::post('/{id}/update', [ProjectController::class, 'update'])->name('update')->middleware('permission:projects,edit');
            Route::delete('/{id}', [ProjectController::class, 'destroy'])->name('destroy')->middleware('permission:projects,delete');
            Route::get('/{id}/members', [ProjectController::class, 'getMembers'])->name('members')->middleware('permission:projects,view');

            // Project Updates (progress/completed/pending/issues/next actions)
            Route::post('/{id}/updates', [ProjectController::class, 'storeUpdate'])->name('updates.store')->middleware('permission:projects,edit');
            Route::post('/{id}/progress/reset', [ProjectController::class, 'resetProgress'])->name('progress.reset')->middleware('permission:projects,edit');

            // Comments
            Route::post('/{id}/comments', [ProjectController::class, 'storeComment'])->name('comments.store')->middleware('permission:projects,edit');
            Route::delete('/comments/{commentId}', [ProjectController::class, 'destroyComment'])->name('comments.destroy')->middleware('permission:projects,edit');

            // Attachments
            Route::post('/{id}/attachments', [ProjectController::class, 'storeAttachment'])->name('attachments.store')->middleware('permission:projects,edit');
            Route::delete('/attachments/{attachmentId}', [ProjectController::class, 'destroyAttachment'])->name('attachments.destroy')->middleware('permission:projects,edit');

            // Milestones
            Route::post('/{id}/milestones', [ProjectController::class, 'storeMilestone'])->name('milestones.store')->middleware('permission:projects,edit');
            Route::post('/milestones/{milestoneId}/update', [ProjectController::class, 'updateMilestone'])->name('milestones.update')->middleware('permission:projects,edit');
            Route::delete('/milestones/{milestoneId}', [ProjectController::class, 'destroyMilestone'])->name('milestones.destroy')->middleware('permission:projects,edit');

            // Risks & Blockers
            Route::post('/{id}/risks', [ProjectController::class, 'storeRisk'])->name('risks.store')->middleware('permission:projects,edit');
            Route::post('/risks/{riskId}/update', [ProjectController::class, 'updateRisk'])->name('risks.update')->middleware('permission:projects,edit');
            Route::delete('/risks/{riskId}', [ProjectController::class, 'destroyRisk'])->name('risks.destroy')->middleware('permission:projects,edit');
        });

        //Assets Route
        // Prefix is 'company-assets' (not 'assets') because public/assets/ is
        // a real directory — Apache's rewrite rule only forwards a request to
        // index.php when the path doesn't match an existing file/directory,
        // so GET /assets never reached Laravel at all. Route *names* stay
        // 'assets.*' so no other file needs to change.
        Route::prefix('company-assets')->name('assets.')->middleware('feature:asset_management')->group(function () {
            Route::get('/', [AssetController::class, 'index'])->name('index')->middleware('permission:assets,view');
            Route::post('/', [AssetController::class, 'store'])->name('store')->middleware('permission:assets,create');
            Route::get('/export', [AssetController::class, 'export'])->name('export')->middleware('permission:assets,export');
            Route::post('/bulk-action', [AssetController::class, 'bulkAction'])->name('bulk-action')->middleware('permission:assets,manage');
            Route::get('/{id}', [AssetController::class, 'show'])->name('show')->middleware('permission:assets,view');
            Route::post('/{id}/update', [AssetController::class, 'update'])->name('update')->middleware('permission:assets,edit');
            Route::delete('/{id}', [AssetController::class, 'destroy'])->name('destroy')->middleware('permission:assets,delete');

            // Lifecycle actions
            Route::post('/{id}/assign', [AssetLifecycleController::class, 'assign'])->name('assign')->middleware('permission:assets,manage');
            Route::post('/{id}/transfer', [AssetLifecycleController::class, 'transfer'])->name('transfer')->middleware('permission:assets,manage');
            Route::post('/{id}/send-for-repair', [AssetLifecycleController::class, 'sendForRepair'])->name('send-for-repair')->middleware('permission:assets,manage');
            Route::post('/repairs/{id}/complete', [AssetLifecycleController::class, 'completeRepair'])->name('repairs.complete')->middleware('permission:assets,manage');
            Route::post('/{id}/report-damage', [AssetLifecycleController::class, 'reportDamage'])->name('report-damage')->middleware('permission:assets,edit');
            Route::post('/damage-reports/{id}/resolve', [AssetLifecycleController::class, 'resolveDamage'])->name('damage-reports.resolve')->middleware('permission:assets,manage');
            Route::post('/{id}/retire', [AssetLifecycleController::class, 'retire'])->name('retire')->middleware('permission:assets,manage');
            Route::post('/{id}/dispose', [AssetLifecycleController::class, 'dispose'])->name('dispose')->middleware('permission:assets,manage');
            // Accept/return are ownership-checked inside the controller (an
            // employee always may act on their own assignment), so only
            // 'view' is required at the route level.
            Route::post('/assignments/{id}/accept', [AssetLifecycleController::class, 'accept'])->name('assignments.accept')->middleware('permission:assets,view');
            Route::post('/assignments/{id}/return', [AssetLifecycleController::class, 'returnAsset'])->name('assignments.return')->middleware('permission:assets,view');

            // Attachments
            Route::post('/{id}/attachments', [AssetAttachmentController::class, 'store'])->name('attachments.store')->middleware('permission:assets,edit');
            Route::delete('/attachments/{attachmentId}', [AssetAttachmentController::class, 'destroy'])->name('attachments.destroy')->middleware('permission:assets,edit');
        });

        // Asset reference data
        Route::prefix('asset-categories')->name('asset-categories.')->middleware('feature:asset_management')->group(function () {
            Route::get('/', [AssetCategoryController::class, 'index'])->name('index')->middleware('permission:assets,view');
            Route::post('/', [AssetCategoryController::class, 'store'])->name('store')->middleware('permission:assets,manage');
            Route::post('/{id}/update', [AssetCategoryController::class, 'update'])->name('update')->middleware('permission:assets,manage');
            Route::delete('/{id}', [AssetCategoryController::class, 'destroy'])->name('destroy')->middleware('permission:assets,manage');
        });

        Route::prefix('asset-types')->name('asset-types.')->middleware('feature:asset_management')->group(function () {
            Route::get('/', [AssetTypeController::class, 'index'])->name('index')->middleware('permission:assets,view');
            Route::post('/', [AssetTypeController::class, 'store'])->name('store')->middleware('permission:assets,manage');
            Route::post('/{id}/update', [AssetTypeController::class, 'update'])->name('update')->middleware('permission:assets,manage');
            Route::delete('/{id}', [AssetTypeController::class, 'destroy'])->name('destroy')->middleware('permission:assets,manage');
        });

        Route::prefix('asset-vendors')->name('asset-vendors.')->middleware('feature:asset_management')->group(function () {
            Route::get('/', [VendorController::class, 'index'])->name('index')->middleware('permission:assets,view');
            Route::post('/', [VendorController::class, 'store'])->name('store')->middleware('permission:assets,manage');
            Route::post('/{id}/update', [VendorController::class, 'update'])->name('update')->middleware('permission:assets,manage');
            Route::delete('/{id}', [VendorController::class, 'destroy'])->name('destroy')->middleware('permission:assets,manage');
        });

        // Employee self-service
        Route::prefix('my-assets')->name('my-assets.')->middleware('feature:asset_management')->group(function () {
            Route::get('/', [AssetController::class, 'myAssets'])->name('index')->middleware('permission:assets,view');
            Route::get('/{id}', [AssetController::class, 'myAssetShow'])->name('show')->middleware('permission:assets,view');
        });

        //Tasks Route
        Route::prefix('tasks')->name('task.')->middleware('feature:task_single,task_group')->group(function () {
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
        // Expense categories carry the company's claim rules (limits / receipt / back-dating), so managing
        // them needs expenses:manage. These routes previously had NO permission check at all — any
        // logged-in employee could reach them.
        Route::prefix('expense-type')->name('expense-type.')->middleware(['feature:expense_management', 'permission:expenses,manage'])->group(function () {
            Route::get('/', [ExpenseTypeController::class, 'index'])->name('index');
            Route::post('/create', [ExpenseTypeController::class, 'store'])->name('create');
            Route::post('/update/{id}', [ExpenseTypeController::class, 'update'])->name('update');
        });


        //Expense Route
        // Self-service routes need at least expenses:view (every default role
        // has it). `create` is NOT used as the gate on purpose: the default
        // Manager role has view+approve but no `create`, yet managers submit
        // their own claims. Ownership is enforced inside the controller.
        // `feature:expense_management` defaults OFF; tenants with pre-existing
        // expense data were backfilled with a tenant_feature_overrides row
        // before this gate went live (see hrm-superadmin tenants:backfill-feature-usage).
        Route::prefix('expense')->name('expense.')->middleware(['feature:expense_management', 'permission:expenses,view'])->group(function () {
            Route::get('/', [ExpenseController::class, 'index'])->name('index');
            Route::delete('/delete/{id}', [ExpenseController::class, 'destroy'])->name('delete')->whereNumber('id');
            Route::get('/view-all', [ExpenseController::class, 'view_all'])->name('view-all');
            Route::post('/create', [ExpenseController::class, 'store'])->name('create');
            Route::post('/update/{id}', [ExpenseController::class, 'update'])->name('update')->whereNumber('id');
            Route::post('/update-status/{id}', [ExpenseController::class, 'updateStatus'])->name('update-status')->whereNumber('id')->middleware('permission:expenses,approve');

            Route::get('/payment/{expenseId}', [ExpenseController::class, 'getPayments'])->name('payments')->whereNumber('expenseId');

            // Employee-owned actions (ownership is enforced in ExpenseService)
            Route::post('/withdraw/{id}', [ExpenseController::class, 'withdraw'])->name('withdraw')->whereNumber('id');
            Route::delete('/{id}/receipts/{attachment}', [ExpenseController::class, 'removeReceipt'])->name('receipts.remove')
                ->whereNumber('id')->where('attachment', 'primary|[0-9]+');
        });

        // Budgets (a cap on approved spend per fiscal year) and reports.
        Route::prefix('expense/budgets')->name('expense.budgets.')->middleware(['feature:expense_management', 'permission:expenses,manage'])->group(function () {
            Route::get('/', [ExpenseBudgetController::class, 'index'])->name('index');
            Route::post('/', [ExpenseBudgetController::class, 'store'])->name('store');
            Route::put('/{id}', [ExpenseBudgetController::class, 'update'])->name('update')->whereNumber('id');
            Route::delete('/{id}', [ExpenseBudgetController::class, 'destroy'])->name('destroy')->whereNumber('id');
        });

        Route::prefix('expense/reports')->name('expense.reports.')->middleware(['feature:expense_management', 'permission:expenses,export'])->group(function () {
            Route::get('/', [ExpenseReportController::class, 'hub'])->name('hub');
            Route::get('/{report}', [ExpenseReportController::class, 'show'])->name('show')->where('report', 'register|summary|ageing');
        });

        // Pay approved reimbursements through payroll instead of a voucher. Switched on/off PER COMPANY by the
        // Super Admin (feature key `expense_payroll_link`, default OFF). Only "send" is feature-gated: the page and
        // "release" stay reachable so reimbursements already routed are never stranded if the switch is turned off.
        Route::prefix('expense/payroll-route')->name('expense.payroll.')->middleware(['feature:expense_management', 'permission:expenses,manage'])->group(function () {
            Route::get('/', [ExpensePayrollRouteController::class, 'index'])->name('index');
            Route::post('/send', [ExpensePayrollRouteController::class, 'send'])->name('send')->middleware('feature:expense_payroll_link');
            Route::post('/release/{id}', [ExpensePayrollRouteController::class, 'release'])->name('release')->whereNumber('id');
        });

        // Bulk approve + payment vouchers. Switched on/off PER COMPANY by the Super Admin
        // (feature key `expense_bulk_payment`, default on) — see config/features.php.
        Route::middleware('feature:expense_bulk_payment')->group(function () {
            Route::post('expense/bulk-status', [ExpenseController::class, 'bulkStatus'])
                ->name('expense.bulk-status')->middleware('permission:expenses,approve');

            Route::prefix('expense/payments')->middleware('permission:expenses,manage')->group(function () {
                Route::get('batch', [PaymentBatchController::class, 'create'])->name('expense.payments.batch');
                Route::get('batch/payable', [PaymentBatchController::class, 'payable'])->name('expense.payments.batch.payable');
                Route::post('batch/preview', [PaymentBatchController::class, 'preview'])->name('expense.payments.batch.preview');
                Route::post('batch/allocate', [PaymentBatchController::class, 'allocate'])->name('expense.payments.batch.allocate');
                Route::post('batch/store', [PaymentBatchController::class, 'store'])->name('expense.payments.batch.store');

                Route::get('vouchers', [PaymentBatchController::class, 'index'])->name('expense.vouchers.index');
                Route::get('vouchers/{id}', [PaymentBatchController::class, 'show'])->name('expense.vouchers.show')->whereNumber('id');
                Route::post('vouchers/{id}/void', [PaymentBatchController::class, 'void'])->name('expense.vouchers.void')->whereNumber('id');
                Route::get('vouchers/{id}/csv', [PaymentBatchController::class, 'csv'])->name('expense.vouchers.csv')->whereNumber('id');
                Route::get('vouchers/{id}/pdf', [PaymentBatchController::class, 'pdf'])->name('expense.vouchers.pdf')->whereNumber('id');
            });
        });

        Route::prefix('expense/payments')->name('expense.payments.')->middleware('permission:expenses,view')->group(function () {

            // Main payment listing page (single blade with modals)
            Route::get('/', [PaymentController::class, 'index'])->name('index');

            // AJAX endpoints that record / change money — expenses:manage only.
            Route::middleware('permission:expenses,manage')->group(function () {
                Route::post('/store', [PaymentController::class, 'store'])->name('store');
                Route::get('/show/{id}', [PaymentController::class, 'show'])->name('show')->whereNumber('id'); // Get single payment for edit
                Route::put('/update/{id}', [PaymentController::class, 'update'])->name('update')->whereNumber('id'); // Update payment
                Route::delete('/destroy/{id}', [PaymentController::class, 'destroy'])->name('destroy')->whereNumber('id'); // Delete payment
                // Declared BEFORE the `/{expenseId}` wildcard below — they were
                // previously shadowed by it and unreachable.
                Route::get('/user-advances', [PaymentController::class, 'getUserPendingAdvances'])->name('user-advances');
                Route::get('/pending-advances', [PaymentController::class, 'getPendingAdvances'])->name('pending-advances');
            });

            Route::get('/advance-summary/{expenseId}', [PaymentController::class, 'getAdvanceSummary'])->name('advance-summary')->whereNumber('expenseId');

            // View payments for a specific expense (alternative to main listing)
            Route::get('/{expenseId}', [PaymentController::class, 'expensePayments'])->name('by-expense')->whereNumber('expenseId');
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

        // Attendance Locations — geofenced check-in points (renamed from
        // "branches" 2026_09_20; purely an attendance concept). See the
        // separate `branch.*` group below for the organizational Branch
        // module.
        Route::prefix('attendance-locations')->name('attendance-location.')->middleware('role:admin,hr')->group(function () {
            Route::get('/', [AttendanceLocationController::class, 'index'])->name('index');
            Route::post('/store', [AttendanceLocationController::class, 'store'])->name('store');
            Route::put('/update/{id}', [AttendanceLocationController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [AttendanceLocationController::class, 'destroy'])->name('delete');
        });

        // Branch — organizational profile (name/address/contact/manager).
        // Read routes are permission-gated (view is available company-wide,
        // e.g. for manager/employee context); mutating routes are also
        // restricted to admin/hr.
        Route::prefix('branches')->name('branch.')->middleware('feature:branches')->group(function () {
            Route::get('/', [BranchController::class, 'index'])->name('index')->middleware('permission:branches,view');
            Route::get('/detail/{id}', [BranchController::class, 'detail'])->name('detail')->middleware('permission:branches,view');
            Route::post('/store', [BranchController::class, 'store'])->name('store')->middleware(['role:admin,hr', 'permission:branches,create']);
            Route::post('/update/{id}', [BranchController::class, 'update'])->name('update')->middleware(['role:admin,hr', 'permission:branches,edit']);
            Route::delete('/delete/{id}', [BranchController::class, 'destroy'])->name('delete')->middleware(['role:admin,hr', 'permission:branches,delete']);
            Route::post('/assign-employees/{id}', [BranchController::class, 'assignEmployees'])->name('assign-employees')->middleware(['role:admin,hr', 'permission:branches,manage']);
        });

        Route::middleware('shifts.custom')->prefix('shift')->name('shift.')->group(function () {
            // Read-only: roster + assignments list — admin / hr / manager
            Route::middleware('role:admin,hr,manager')->group(function () {
                Route::get('/roster', [ShiftController::class, 'roster'])->name('roster');
                Route::get('/user-shifts/data', [ShiftController::class, 'getUserShiftsData'])->name('user-shifts.data');
                Route::get('/user-shifts/export', [ShiftController::class, 'exportUserShifts'])->name('user-shifts.export');
                Route::get('/get-users-by-type', [ShiftController::class, 'getUsersByType'])->name('get-users-by-type');
                Route::post('/assignment-conflicts', [ShiftController::class, 'checkAssignmentConflicts'])->name('assignment-conflicts');
                Route::get('/assignments/history', [ShiftController::class, 'assignmentHistory'])->name('assignments.history');
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
                Route::post('/assignments/{id}/end-permanent', [ShiftController::class, 'endPermanentShift'])->name('assignments.end-permanent');
            });
        });
        Route::prefix('my-payroll')->name('my-payroll.')->middleware(['feature:payroll', 'permission:payroll,view'])->group(function () {
            Route::get('/salary-slips', [MonthlyPayrollController::class, 'mySalarySlips'])->name('my-salary-slips');
            Route::get('/download/{id}', [MonthlyPayrollController::class, 'downloadSalarySlip'])->name('download-salary-slip');
            Route::get('/view/{id}', [MonthlyPayrollController::class, 'viewSalarySlip'])->name('view-salary-slip');
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
            Route::post('/{id}/recalculate-preview', [MonthlyPayrollController::class, 'recalculatePreview'])->name('recalculate-preview')->middleware('permission:payroll,edit');
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

        Route::prefix('attendance-regularization')->name('attendance-regularization.')->middleware('feature:regularization')->group(function () {
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

        Route::prefix('requests')->name('requests.')->middleware('feature:wfh_travel')->group(function () {
            Route::get('/', [RequestController::class, 'index'])->name('index');
            Route::post('/', [RequestController::class, 'store'])->name('store');
            Route::post('/{id}', [RequestController::class, 'update'])->name('update');
            Route::post('/{id}/destroy', [RequestController::class, 'destroy'])->name('destroy');
            Route::delete('/attachments/{attachmentId}', [RequestController::class, 'deleteAttachment'])
                ->name('delete-attachment');
            Route::get('/attachments/{attachmentId}/download', [RequestController::class, 'downloadAttachment'])
                ->name('download-attachment');
            Route::get('/stats', [RequestController::class, 'getStats'])->name('stats');
            Route::get('/export', [RequestController::class, 'export'])->name('export');
        });

        Route::middleware('feature:wfh_travel')->group(function () {
            Route::get('/manager/requests', [RequestController::class, 'managerIndex'])->name('manager.requests')->middleware('permission:requests,view');
            Route::get('/manager/requests/{id}', [RequestController::class, 'managerShow'])->name('manager.requests.show')->middleware('permission:requests,view');
            Route::post('/manager/requests/{id}/approve', [RequestController::class, 'approve'])->middleware('permission:requests,approve');
            Route::post('/manager/requests/{id}/reject', [RequestController::class, 'reject'])->middleware('permission:requests,approve');
            Route::post('/manager/requests/bulk-approve', [RequestController::class, 'bulkApprove'])->middleware('permission:requests,approve');
            Route::get('/manager/requests/export', [RequestController::class, 'managerExport'])->middleware('permission:requests,view');
            Route::get('/manager/stats', [RequestController::class, 'managerStats'])->middleware('permission:requests,view');
        });

        Route::prefix('announcement')->name('announcement.')->middleware('feature:announcements')->group(function () {
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

        Route::prefix('broadcast')->name('broadcast.')->middleware('feature:broadcast_notifications')->group(function () {
            // "Send Broadcast" is now a drawer on the index/history page
            // itself (see resources/views/client/broadcast/partials/
            // send-drawer.blade.php) — no separate /create page.
            Route::get('/', [BroadcastController::class, 'index'])->name('index');
            Route::post('/store', [BroadcastController::class, 'store'])->name('store')->middleware('permission:broadcasts,create');
            Route::post('/preview-count', [BroadcastController::class, 'previewCount'])->name('preview-count')->middleware(['permission:broadcasts,create', 'throttle:30,1']);
            Route::get('/{id}', [BroadcastController::class, 'show'])->name('show')->middleware('permission:broadcasts,view');
            Route::post('/{id}/cancel', [BroadcastController::class, 'cancel'])->name('cancel')->middleware('permission:broadcasts,edit');

            // recipient-facing web notification center — every authenticated user
            Route::get('/notifications/list', [BroadcastNotificationCenterController::class, 'index'])->name('notifications.index');
            Route::get('/notifications/unread-count', [BroadcastNotificationCenterController::class, 'unreadCount'])->name('notifications.unread-count');
            Route::post('/notifications/{id}/read', [BroadcastNotificationCenterController::class, 'markAsRead'])->name('notifications.read');
            Route::post('/notifications/mark-all-read', [BroadcastNotificationCenterController::class, 'markAllAsRead'])->name('notifications.read-all');
        });

        Route::prefix('leave-credit')->name('leave-credit.')->middleware('feature:leave_management')->group(function () {
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
        
        Route::prefix('meetings')->name('meetings.')->middleware('feature:meetings')->group(function () {
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

        Route::prefix('meetings/mom')->name('meetings.mom.')->middleware('feature:meetings')->group(function () {
            Route::get('/create/{id}', [MeetingMinuteController::class, 'create'])->name('create')->middleware('permission:meetings,edit');
            Route::post('/store', [MeetingMinuteController::class, 'store'])->name('store')->middleware('permission:meetings,edit');
            Route::post('/{id}/reopen', [MeetingMinuteController::class, 'reopen'])->name('reopen')->middleware('permission:meetings,edit');
        });

        // Overtime Routes
        Route::prefix('overtime')->middleware('feature:overtime')->group(function () {
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

        // Tenant performance-scoring policy (weights + penalty thresholds)
        Route::middleware('role:admin,hr')->prefix('performance-policy')->name('performance-policy.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Settings\PerformancePolicyController::class, 'index'])->name('index');
            Route::put('/', [\App\Http\Controllers\Settings\PerformancePolicyController::class, 'update'])->name('update');
        });

        // Tenant shift settings (custom-shifts toggle + fixed company shift)
        Route::middleware('role:admin,hr')->prefix('shift-settings')->name('shift-settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Settings\ShiftSettingsController::class, 'index'])->name('index');
            Route::put('/', [\App\Http\Controllers\Settings\ShiftSettingsController::class, 'update'])->name('update');
        });

        // Combined Employee ID + Notice Period page (Basic Setup). One sidebar
        // link; each card still posts to its own controller's update route.
        Route::middleware('role:admin,hr')->get('workforce-settings', [\App\Http\Controllers\Settings\WorkforceSettingsController::class, 'index'])->name('workforce-settings.index');

        // Company-wide Notice Period (Basic Setup) — default used to compute
        // the minimum last working date on every offboarding request.
        Route::middleware('role:admin,hr')->prefix('notice-period-settings')->name('notice-period-settings.')->group(function () {
            Route::put('/', [\App\Http\Controllers\Settings\NoticePeriodSettingsController::class, 'update'])->name('update');
        });

        // Tenant-configurable prefix for auto-generated employee IDs (e.g. SH000123).
        Route::middleware('role:admin,hr')->prefix('employee-id-settings')->name('employee-id-settings.')->group(function () {
            Route::put('/', [\App\Http\Controllers\Settings\EmployeeIdSettingsController::class, 'update'])->name('update');
        });

        // Multiple-punches-per-day toggle (Company Policies) — tenants.allow_multiple_punches.
        Route::middleware('role:admin,hr')->prefix('multiple-punches-settings')->name('multiple-punches-settings.')->group(function () {
            Route::put('/', [\App\Http\Controllers\Settings\MultiplePunchesSettingsController::class, 'update'])->name('update');
        });

        // Day classification (present/half-day ratios) standalone card on the
        // Company Policies page — writes a versioned attendance_policies row,
        // carrying forward the rest of the currently-effective policy.
        Route::middleware('role:admin,hr')->prefix('day-classification-settings')->name('day-classification-settings.')->group(function () {
            Route::put('/', [\App\Http\Controllers\Settings\DayClassificationSettingsController::class, 'update'])->name('update');
        });

        // Late Arrival / Early Leaving deduction rules — two independent
        // cards on the Company Policies page, each posting to its own
        // route; grace-mode fields are owned by the Late Arrival route.
        Route::middleware('role:admin,hr')->prefix('late-policy-settings')->name('late-policy-settings.')->group(function () {
            Route::put('/', [\App\Http\Controllers\Settings\LatePolicySettingsController::class, 'update'])->name('update');
        });
        Route::middleware('role:admin,hr')->prefix('early-leaving-policy-settings')->name('early-leaving-policy-settings.')->group(function () {
            Route::put('/', [\App\Http\Controllers\Settings\EarlyLeavingPolicySettingsController::class, 'update'])->name('update');
        });

        // Working-time thresholds (advisory-only, not yet enforced) standalone
        // card on the Company Policies page — writes a versioned
        // attendance_policies row, carrying forward the rest unchanged.
        Route::middleware('role:admin,hr')->prefix('working-time-thresholds-settings')->name('working-time-thresholds-settings.')->group(function () {
            Route::put('/', [\App\Http\Controllers\Settings\WorkingTimeThresholdsSettingsController::class, 'update'])->name('update');
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

            // Biometric terminals (SBXPC bridge) — gated by the attendance_biometric plan feature
            Route::middleware('feature:attendance_biometric')->group(function () {
                Route::get('/biometric', [\App\Http\Controllers\Settings\BiometricController::class, 'index'])->name('biometric.index');
                Route::post('/biometric/devices', [\App\Http\Controllers\Settings\BiometricController::class, 'storeDevice'])->name('biometric.devices.store');
                Route::put('/biometric/devices/{device}', [\App\Http\Controllers\Settings\BiometricController::class, 'updateDevice'])->name('biometric.devices.update');
                Route::delete('/biometric/devices/{device}', [\App\Http\Controllers\Settings\BiometricController::class, 'destroyDevice'])->name('biometric.devices.destroy');
                Route::post('/biometric/devices/{device}/bridge-key', [\App\Http\Controllers\Settings\BiometricController::class, 'generateBridgeKey'])->name('biometric.devices.bridge-key');
                Route::post('/biometric/devices/{device}/push-url', [\App\Http\Controllers\Settings\BiometricController::class, 'pushUrl'])->name('biometric.devices.push-url');
                Route::get('/biometric/devices/{device}/config', [\App\Http\Controllers\Settings\BiometricController::class, 'downloadConfig'])->name('biometric.devices.config');
                Route::get('/biometric/devices/{device}/enrollments', [\App\Http\Controllers\Settings\BiometricController::class, 'enrollments'])->name('biometric.enrollments');
                Route::post('/biometric/devices/{device}/enrollments', [\App\Http\Controllers\Settings\BiometricController::class, 'mapEnrollment'])->name('biometric.enrollments.map');
                Route::post('/biometric/devices/{device}/enrollments/auto', [\App\Http\Controllers\Settings\BiometricController::class, 'autoMap'])->name('biometric.enrollments.auto');
                Route::post('/biometric/devices/{device}/enrollments/bulk-repush', [\App\Http\Controllers\Settings\BiometricController::class, 'bulkRepushEnrollments'])->name('biometric.enrollments.bulk-repush');
                Route::post('/biometric/devices/{device}/enrollments/bulk-remove', [\App\Http\Controllers\Settings\BiometricController::class, 'bulkRemoveEnrollments'])->name('biometric.enrollments.bulk-remove');
                Route::post('/biometric/devices/{device}/roster/sync', [\App\Http\Controllers\Settings\BiometricController::class, 'syncRoster'])->name('biometric.roster.sync');
                Route::post('/biometric/enrollments/{enrollment}/card', [\App\Http\Controllers\Settings\BiometricController::class, 'setCard'])->name('biometric.enrollments.card');
                Route::post('/biometric/enrollments/{enrollment}/repush', [\App\Http\Controllers\Settings\BiometricController::class, 'repushEnrollment'])->name('biometric.enrollments.repush');
                Route::post('/biometric/enrollments/{enrollment}/remove', [\App\Http\Controllers\Settings\BiometricController::class, 'removeEnrollment'])->name('biometric.enrollments.remove');
                Route::delete('/biometric/enrollments/{enrollment}', [\App\Http\Controllers\Settings\BiometricController::class, 'unmapEnrollment'])->name('biometric.enrollments.unmap');
                Route::get('/biometric/punches', [\App\Http\Controllers\Settings\BiometricController::class, 'punches'])->name('biometric.punches');
                Route::post('/biometric/punches/{punch}/reprocess', [\App\Http\Controllers\Settings\BiometricController::class, 'reprocessPunch'])->name('biometric.punches.reprocess');
            });
        });

        // Tier 2 / T2-F — attendance analytics dashboard + anomaly review
        Route::middleware('role:admin,hr,manager')->prefix('analytics')->name('analytics.attendance.')->group(function () {
            Route::get('/attendance', [\App\Http\Controllers\Analytics\AttendanceAnalyticsController::class, 'index'])->name('index');
            Route::post('/attendance/anomaly/{id}', [\App\Http\Controllers\Analytics\AttendanceAnalyticsController::class, 'reviewAnomaly'])->name('anomaly.review');
        });
        
        Route::prefix('loan')->middleware('feature:loan_management')->group(function () {

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
        Route::prefix('offboarding')->name('offboarding.')->middleware('feature:offboarding')->group(function () {
            // Main routes (static paths first)
            Route::get('/create', [OffboardingController::class, 'create'])->name('create')->middleware('permission:offboarding,create');
            Route::get('/manager', [OffboardingController::class, 'managerIndex'])->name('manager')->middleware('permission:offboarding,view');
            Route::get('/index', [OffboardingController::class, 'adminIndex'])->name('index')->middleware('permission:offboarding,view');
            Route::get('/employee/index', [OffboardingController::class, 'employeeIndex'])->name('employee')->middleware('permission:offboarding,view');

            Route::post('/store', [OffboardingController::class, 'store'])->name('store')->middleware('permission:offboarding,create');

            // Parameterized routes (dynamic paths last)
            Route::get('/{id}', [OffboardingController::class, 'show'])->name('show')->middleware('permission:offboarding,view');

            // Approval decision — resolves whichever level (manager/HR) is
            // currently pending via ApprovalService, one route for both.
            Route::post('/{id}/decide', [OffboardingController::class, 'decide'])->name('decide')->middleware('permission:offboarding,approve');
            Route::post('/{id}/cancel', [OffboardingController::class, 'cancel'])->name('cancel')->middleware('permission:offboarding,edit');

            // Knowledge Transfer routes
            Route::post('/{id}/knowledge-transfer/start', [OffboardingController::class, 'startKnowledgeTransfer'])->name('knowledge-transfer.start')->middleware('permission:offboarding,edit');
            Route::post('/{id}/knowledge-transfer/complete', [OffboardingController::class, 'completeKnowledgeTransfer'])->name('knowledge-transfer.complete')->middleware('permission:offboarding,edit');

            // Clearance routes
            Route::post('/{id}/clearance-task/{taskId}/update', [OffboardingController::class, 'updateClearanceTask'])->name('clearance-task.update')->middleware('permission:offboarding,edit');
            Route::post('/{id}/stage/reopen', [OffboardingController::class, 'reopenStage'])->name('stage.reopen')->middleware('permission:offboarding,edit');

            // Exit Interview routes
            Route::post('/{id}/exit-interview', [OffboardingController::class, 'recordExitInterview'])->name('exit-interview.record')->middleware('permission:offboarding,edit');
            Route::post('/{id}/exit-interview/skip', [OffboardingController::class, 'skipExitInterview'])->name('exit-interview.skip')->middleware('permission:offboarding,edit');

            // Notice period override routes
            Route::post('/{id}/notice-override', [OffboardingController::class, 'requestNoticeOverride'])->name('notice-override.request')->middleware('permission:offboarding,edit');
            Route::post('/{id}/notice-override/{overrideId}/decide', [OffboardingController::class, 'decideNoticeOverride'])->name('notice-override.decide')->middleware('permission:offboarding,edit');

            // Settlement routes
            Route::post('/{id}/settlement/line', [OffboardingController::class, 'addSettlementLine'])->name('settlement.add-line')->middleware('permission:offboarding,edit');
            Route::post('/{id}/settlement/line/{itemId}/override', [OffboardingController::class, 'overrideSettlementLine'])->name('settlement.override-line')->middleware('permission:offboarding,edit');
            Route::post('/{id}/settlement/finalize', [OffboardingController::class, 'finalizeSettlement'])->name('settlement.finalize')->middleware('permission:offboarding,edit');
            Route::post('/{id}/settlement/mark-paid', [OffboardingController::class, 'markSettlementPaid'])->name('settlement.mark-paid')->middleware('permission:offboarding,edit');

            // Status change routes
            Route::post('/{id}/complete', [OffboardingController::class, 'complete'])->name('complete')->middleware('permission:offboarding,edit');

            // Remarks route (AJAX)
            Route::post('/{id}/remarks', [OffboardingController::class, 'updateRemarks'])->name('remarks.update')->middleware('permission:offboarding,edit');
        });
        
        Route::prefix('performance')->name('performance.')->middleware(['feature:kpi_performance', 'permission:performance,view'])->group(function () {
            Route::get('/my-dashboard', [PerformanceController::class, 'myPerformance'])->name('my-dashboard');
            Route::get('/employee/{userId}', [PerformanceController::class, 'individualReport'])->name('individual');
            Route::get('/employee/{userId}/daily-detail', [PerformanceController::class, 'dailyDetail'])->name('daily-detail');
            Route::get('/team', [PerformanceController::class, 'teamReport'])->name('team');
        });
        
        Route::prefix('job-openings')->name('job-openings.')->middleware('feature:recruitment')->group(function () {
            Route::get('/', [JobOpeningController::class, 'index'])->name('index')->middleware('permission:recruitment,view');
            Route::post('/store', [JobOpeningController::class, 'store'])->name('store')->middleware('permission:recruitment,create');
            Route::get('/show/{id}', [JobOpeningController::class, 'show'])->name('show')->middleware('permission:recruitment,view');
            Route::get('/edit/{id}', [JobOpeningController::class, 'edit'])->name('edit')->middleware('permission:recruitment,view');
            Route::post('/update/{id}', [JobOpeningController::class, 'update'])->name('update')->middleware('permission:recruitment,edit');
            Route::post('/destroy/{id}', [JobOpeningController::class, 'destroy'])->name('destroy')->middleware('permission:recruitment,delete');
            Route::post('/{id}/publish', [JobOpeningController::class, 'publish'])->name('publish')->middleware('permission:recruitment,edit');
            Route::post('/{id}/close', [JobOpeningController::class, 'close'])->name('close')->middleware('permission:recruitment,edit');
            Route::post('/{id}/duplicate', [JobOpeningController::class, 'duplicate'])->name('duplicate')->middleware('permission:recruitment,create');
            Route::get('/{id}/applications', [JobOpeningController::class, 'getApplications'])->name('applications')->middleware('permission:recruitment,view');
        });
        Route::prefix('recruitment')->name('recruitment.')->middleware('feature:recruitment')->group(function () {
            Route::post('/applications/{id}/shortlist', [JobOpeningController::class, 'shortlist'])->name('shortlist')->middleware('permission:recruitment,edit');
            Route::post('/applications/{id}/reject', [JobOpeningController::class, 'reject'])->name('reject')->middleware('permission:recruitment,edit');
            Route::post('/{id}/schedule-interview', [JobOpeningController::class, 'scheduleInterview'])->name('schedule-interview')->middleware('permission:recruitment,edit');
            Route::post('/interviews/{id}/cancel', [JobOpeningController::class, 'cancelInterview'])->name('cancel-interview')->middleware('permission:recruitment,edit');
            Route::post('/interviews/{id}/reschedule', [JobOpeningController::class, 'rescheduleInterview'])->name('reschedule-interview')->middleware('permission:recruitment,edit');
            Route::post('/interviews/{id}/feedback', [JobOpeningController::class, 'submitFeedback'])->name('submit-feedback')->middleware('permission:recruitment,edit');
            Route::post('/applications/{id}/release-offer', [JobOpeningController::class, 'releaseOffer'])->name('release-offer')->middleware('permission:recruitment,edit');
            Route::post('/applications/{id}/offer-accepted', [JobOpeningController::class, 'offerAccepted'])
                ->name('offer-accepted')->middleware('permission:recruitment,edit');
            Route::post('/applications/{id}/offer-rejected', [JobOpeningController::class, 'offerRejected'])
                ->name('offer-rejected')->middleware('permission:recruitment,edit');
            Route::get('/applications/{id}/interview-details', [JobOpeningController::class, 'interviewDetails'])->name('interview-details')->middleware('permission:recruitment,view');
        });
        Route::prefix('recruitment/onboarding')->name('onboarding.')->middleware('feature:recruitment')->group(function () {
            Route::get('/applications/{id}', [OnboardingController::class, 'show'])->name('show')->middleware('permission:onboarding,view');
            Route::post('/{assignmentId}/documents', [OnboardingController::class, 'uploadDocument'])->name('documents.upload')->middleware('permission:onboarding,edit');
            Route::post('/documents/{documentId}/verify', [OnboardingController::class, 'verifyDocument'])->name('documents.verify')->middleware('permission:onboarding,edit');
            Route::post('/documents/{documentId}/reject', [OnboardingController::class, 'rejectDocument'])->name('documents.reject')->middleware('permission:onboarding,edit');
            Route::post('/task-items/{itemId}', [OnboardingController::class, 'updateTaskItem'])->name('task-items.update')->middleware('permission:onboarding,edit');
            Route::post('/{assignmentId}/complete', [OnboardingController::class, 'complete'])->name('complete')->middleware('permission:onboarding,edit');
            Route::post('/{assignmentId}/hire', [OnboardingController::class, 'hire'])->name('hire')->middleware('permission:onboarding,manage');
        });

      
        Route::prefix('/performance/reviews')->name('performance.reviews.')->middleware('feature:kpi_performance')->group(function () {
            Route::get('/', [ManagerPerformanceReviewController::class, 'index'])->name('index')->middleware('permission:performance_reviews,view');
            Route::post('/store/{userId}', [ManagerPerformanceReviewController::class, 'store'])->name('store')->middleware('permission:performance_reviews,create');
            Route::get('/show/{id}', [ManagerPerformanceReviewController::class, 'show'])->name('show')->middleware('permission:performance_reviews,view');
            Route::put('/update/{id}', [ManagerPerformanceReviewController::class, 'update'])->name('update')->middleware('permission:performance_reviews,edit');
            Route::delete('/destroy/{id}', [ManagerPerformanceReviewController::class, 'destroy'])->name('destroy')->middleware('permission:performance_reviews,delete');
            // Employee acknowledging their own review — not a 'view'/'edit'
            // grant on the module, guarded in-controller instead (only the
            // reviewed employee may acknowledge their own row).
            Route::post('/acknowledge/{id}', [ManagerPerformanceReviewController::class, 'acknowledge'])->name('acknowledge');
            Route::get('/pending-count', [ManagerPerformanceReviewController::class, 'pendingCount'])->name('pending-count')->middleware('permission:performance_reviews,view');
            Route::get('/export', [ManagerPerformanceReviewController::class, 'export'])->name('export')->middleware('permission:performance_reviews,view');
        });
        
        Route::prefix('report')->name('report.')->middleware('role:admin,hr,manager')->group(function () {
            // Attendance Reports — each report is gated by its own module's feature key
            // so a tenant only ever sees reports for modules they actually have.
            Route::middleware('feature:attendance')->group(function () {
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

                // Clock In/Out log — one row per punch (multiple clock in/out), with location details.
                Route::get('/attendance/punches', [\App\Http\Controllers\Report\PunchReportController::class, 'index'])->name('attendance.punches.index');
                Route::get('/attendance/punches/export', [\App\Http\Controllers\Report\PunchReportController::class, 'export'])->name('attendance.punches.export');
            });

            Route::middleware('feature:overtime')->group(function () {
                Route::get('/overtime/monthly', [AttendanceReportController::class, 'overtimeMonthlyReport'])->name('overtime.monthly.index');
            });

            // Shift Report (monthly): which employee is on which shift on which date
            Route::middleware('feature:fixed_shift,custom_shift')->group(function () {
                Route::get('/attendance/shift-monthly', [ShiftReportController::class, 'monthly'])->name('attendance.shift-monthly.index');
                Route::get('/attendance/shift-monthly/export', [ShiftReportController::class, 'monthlyExport'])->name('attendance.shift-monthly.export');
            });

            // Task Reports
            Route::middleware('feature:task_single,task_group')->group(function () {
                Route::get('/task-project', [TaskReportController::class, 'index'])->name('task-project.index');

                Route::prefix('task')->name('task.')->group(function () {
                    Route::get('/day-wise', [TaskReportController::class, 'dayWiseTaskReport'])->name('day-wise');
                    Route::get('/day-wise/export', [TaskReportController::class, 'exportDayWiseCsv'])->name('day-wise.export');
                    Route::get('/employee-monthly', [TaskReportController::class, 'employeeMonthlyReport'])->name('employee-monthly');
                    Route::get('/employee-monthly/export', [TaskReportController::class, 'exportEmployeeMonthlyCsv'])->name('employee-monthly.export');
                    Route::get('/employee-date-wise', [TaskReportController::class, 'employeeDateWiseReport'])->name('employee-date-wise');
                    Route::get('/employee-date-wise/export', [TaskReportController::class, 'exportEmployeeDateWiseCsv'])->name('employee-date-wise.export');
                    Route::get('/monthly-task-detail', [TaskReportController::class, 'monthlyTaskDetailReport'])->name('monthly-task-detail');
                    Route::get('/monthly-task-detail/export', [TaskReportController::class, 'exportMonthlyDetailCsv'])->name('monthly-task-detail.export');
                });
            });

            // Project Reports
            Route::middleware('feature:project_management')->group(function () {
                Route::get('/project/summary', [ProjectReportController::class, 'summary'])->name('project.summary.index');
                Route::get('/project/summary/export', [ProjectReportController::class, 'summaryExport'])->name('project.summary.export');
                Route::get('/project/progress', [ProjectReportController::class, 'progress'])->name('project.progress.index');
                Route::get('/project/progress/export', [ProjectReportController::class, 'progressExport'])->name('project.progress.export');
                Route::get('/project/task-performance', [ProjectReportController::class, 'taskPerformance'])->name('project.task-performance.index');
                Route::get('/project/task-performance/export', [ProjectReportController::class, 'taskPerformanceExport'])->name('project.task-performance.export');
                Route::get('/project/timeline', [ProjectReportController::class, 'timeline'])->name('project.timeline.index');
                Route::get('/project/timeline/export', [ProjectReportController::class, 'timelineExport'])->name('project.timeline.export');
            });

            // Asset Reports
            Route::middleware('feature:asset_management')->group(function () {
                Route::get('/asset/register', [AssetReportController::class, 'register'])->name('asset.register.index');
                Route::get('/asset/register/export', [AssetReportController::class, 'exportRegister'])->name('asset.register.export');
                Route::get('/asset/employee-wise', [AssetReportController::class, 'employeeWise'])->name('asset.employee-wise.index');
                Route::get('/asset/summary', [AssetReportController::class, 'summary'])->name('asset.summary.index');
                Route::get('/asset/warranty-expiry', [AssetReportController::class, 'warrantyExpiry'])->name('asset.warranty-expiry.index');
            });

            // Payroll Reports (money-sensitive — PayrollReportController itself re-checks the
            // payroll/loans RBAC scope per report, this route group only guards the page).
            // Payroll is out of scope for this feature-gating pass — left ungated.
            Route::get('/payroll/{report}', [PayrollReportController::class, 'show'])->name('payroll.show')
                ->where('report', 'monthly|structure|loan|summary');

            // Leave Reports (leave balance/register/summary, regularization, WFH & Travel)
            Route::middleware('feature:leave_management')->group(function () {
                Route::get('/leave/{report}', [LeaveReportController::class, 'show'])->name('leave.show')
                    ->where('report', 'balance|register|summary|regularization|wfh-travel');
            });
        });

    });
});
