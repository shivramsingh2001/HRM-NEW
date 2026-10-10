@extends('client.layout.master')

@section('style')
    <style>
        /* Report tabs — compact segmented pill bar: ONE rounded outer border, equal-width segments,
           active = blue gradient (same #0D6EFD→#0D6EFD as the theme buttons) / white text, inactive = white / dark text. */
        #reportTab {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 3px;
            padding: 3px;
            background: #fff;
            border: 1px solid #dfe5f0 !important;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        }

        #reportTab .nav-item {
            flex: 1 1 0;
            min-width: 0;
            border: 0 !important;
            margin: 0;
        }

        #reportTab .nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            width: 100%;
            height: 32px;
            padding: 0 10px;
            margin: 0;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
            color: #1a2236;
            background: #fff;
            border: 0 !important;
            border-radius: 20px;
            transition: background .18s ease, color .18s ease;
        }

        #reportTab .nav-link i { font-size: 12px; margin: 0 !important; }

        #reportTab .nav-link:hover:not(.active) { background: #f1f5fd; color: #0D6EFD; }

        #reportTab .nav-link.active,
        #reportTab .nav-link.active:hover {
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            color: #fff;
            box-shadow: 0 2px 6px rgba(13, 110, 253, .25);
        }

        @media (max-width: 767.98px) {
            #reportTab { flex-wrap: wrap; }
            #reportTab .nav-item { flex: 1 1 calc(50% - 3px); }
        }

        /* Report Cards Styles - Compact Version */
        .report-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .report-card {
            background: white;
            border-radius: 12px;
            padding: 11px 13px;
            border: 1px solid #eef2f6;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: default;
            position: relative;
            overflow: hidden;
            min-height: 150px;
            display: flex;
            flex-direction: column;
        }

        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0D6EFD, #0D6EFD);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .report-card:hover::before {
            opacity: 1;
        }

        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border-color: #d1d5db;
        }

        .report-card .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .report-card:hover .card-icon {
            transform: scale(1.05);
        }

        .card-icon.primary {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.12), rgba(79, 70, 229, 0.05));
            color: var(--icon-color, #0D6EFD);
        }

        .card-icon.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(16, 185, 129, 0.05));
            color: var(--icon-color, #0D6EFD);
        }

        .card-icon.info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(59, 130, 246, 0.05));
            color: var(--icon-color, #0D6EFD);
        }

        .card-icon.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.05));
            color: var(--icon-color, #0D6EFD);
        }

        .card-icon.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(239, 68, 68, 0.05));
            color: #475569;
        }

        .card-icon.purple {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.12), rgba(139, 92, 246, 0.05));
            color: var(--icon-color, #0D6EFD);
        }

        .report-card .card-title {
            font-size: 11px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .report-card .card-description {
            font-size: 10px;
            color: #64748b;
            line-height: 1.4;
            flex-grow: 1;
            margin-bottom: 8px;
        }

        .report-card .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0 0;
            border-top: 1px solid #f1f5f9;
            margin-top: auto;
        }

        .report-card .badge {
            font-size: 8.5px;
            padding: 2px 7px;
            border-radius: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-primary {
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .badge-success {
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .badge-info {
            background: #dbeafe;
            color: #0D6EFD;
        }

        .badge-warning {
            background: #bfd3f7;
            color: #0D6EFD;
        }

        .badge-danger {
            background: #e2e8f0;
            color: #475569;
        }

        .badge-purple {
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .report-card .btn-generate {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 9.5px;
            font-weight: 600;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD) !important;
            color: white;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            line-height: 1.6;
        }

        .report-card .btn-generate:hover {
            background: #0B5ED7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .report-card .btn-generate i {
            font-size: 10px;
        }

        .report-card .btn-generate.coming-soon {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Quick Stats Section - Compact */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .quick-stat-card {
            background: white;
            border-radius: 10px;
            padding: 8px 11px;
            border: 1px solid #eef2f6;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
        }

        .quick-stat-card:hover {
            border-color: #d1d5db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .quick-stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }

        .quick-stat-icon.purple {
            background: #EFF6FF;
            color: var(--icon-color, #0D6EFD);
        }

        .quick-stat-icon.blue {
            background: #dbeafe;
            color: var(--icon-color, #0D6EFD);
        }

        .quick-stat-icon.green {
            background: #EFF6FF;
            color: var(--icon-color, #0D6EFD);
        }

        .quick-stat-icon.orange {
            background: #bfd3f7;
            color: var(--icon-color, #0D6EFD);
        }

        .quick-stat-content {
            flex: 1;
        }

        .quick-stat-number {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .quick-stat-label {
            font-size: 9.5px;
            color: #64748b;
            font-weight: 500;
        }

      

        /* Alert Compact */
        .alert {
            padding: 12px 16px !important;
            border-radius: 10px !important;
        }

        .alert h6 {
            font-size: 10.5px !important;
            margin-bottom: 2px !important;
        }

        .alert p {
            font-size: 10px !important;
        }

        .alert i {
            font-size: 16px !important;
        }

        /* Coming Soon Overlay */
        .report-card.coming-soon-card {
            opacity: 0.7;
            position: relative;
        }

        .report-card.coming-soon-card .coming-soon-overlay {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 8px;
            padding: 2px 7px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .report-cards-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .report-card {
                min-height: 130px;
                padding: 10px 11px;
            }

          
        }

        @media (max-width: 480px) {
            .quick-stats {
                grid-template-columns: 1fr 1fr;
            }

            .quick-stat-card {
                padding: 7px 8px;
            }

            .quick-stat-number {
                font-size: 12px;
            }

            .report-card .card-title {
                font-size: 10.5px;
            }
        }
    </style>
@endsection

@section('content-area')
     <x-ui.page-header title="Report">
         <x-slot:actions>
             <span class="badge badge-info-custom">
                 <i class="feather-calendar me-1"></i> {{ now()->format('F Y') }}
             </span>
         </x-slot:actions>
     </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        @php
            // The Expense reports are behind permission:expenses,export — only offer the tab to people who can open them.
            $canExpenseReports = app(\App\Services\RbacService::class)->can(auth()->user(), 'expenses', 'export') && app(\App\Services\FeatureService::class)->enabledForCurrentTenant('expense_management');
            // Payroll is money-sensitive: only offer the tab when the caller's payroll,view scope is
            // company-wide (admin/hr) — a manager's scope is 'own', same rule PayrollReportController enforces.
            $canPayrollReports = app(\App\Services\RbacService::class)->scopeFor(auth()->user(), 'payroll', 'view') === 'company';

            // Each report category is only offered if its source module is enabled for this tenant's plan.
            $reportFeatures = app(\App\Services\FeatureService::class);
            // Any attendance method (manual / face / biometric) produces attendance data.
            $canAttendanceReports = $reportFeatures->enabledForCurrentTenant('attendance')
                || $reportFeatures->enabledForCurrentTenant('attendance_face')
                || $reportFeatures->enabledForCurrentTenant('attendance_biometric');
            $canProjectReports = $reportFeatures->enabledForCurrentTenant('project_management');
            $canTaskReports = $reportFeatures->enabledForCurrentTenant('task_single') || $reportFeatures->enabledForCurrentTenant('task_group');
            $canAssetReports = $reportFeatures->enabledForCurrentTenant('asset_management');
            $canLeaveReports = $reportFeatures->enabledForCurrentTenant('leave_management');
            $canOvertimeReport = $reportFeatures->enabledForCurrentTenant('overtime');
            $canShiftReport = $reportFeatures->enabledForCurrentTenant('fixed_shift') || $reportFeatures->enabledForCurrentTenant('custom_shift');
            $canRegularizationReport = $reportFeatures->enabledForCurrentTenant('regularization');
            $canWfhTravelReport = $reportFeatures->enabledForCurrentTenant('wfh_travel');
        @endphp

        <!-- Report Category Tabs -->
        <div class="mb-3">
            <div>
                <ul class="nav nav-tabs w-100 text-center customers-nav-tabs" id="reportTab" role="tablist">
                    @if ($canAttendanceReports)
                        <li class="nav-item flex-fill border-top">
                            <a class="nav-link active" data-bs-toggle="tab" href="#attendanceReportTab">
                                <i class="feather-calendar me-1"></i> Attendance Report
                            </a>
                        </li>
                    @endif
                    @if ($canProjectReports)
                        <li class="nav-item flex-fill border-top">
                            <a class="nav-link" data-bs-toggle="tab" href="#projectReportTab">
                                <i class="feather-briefcase me-1"></i> Project Report
                            </a>
                        </li>
                    @endif
                    @if ($canTaskReports)
                        <li class="nav-item flex-fill border-top">
                            <a class="nav-link" data-bs-toggle="tab" href="#taskReportTab">
                                <i class="feather-check-square me-1"></i> Task Report
                            </a>
                        </li>
                    @endif
                    @if ($canExpenseReports)
                        <li class="nav-item flex-fill border-top">
                            <a class="nav-link" data-bs-toggle="tab" href="#expenseReportTab">
                                <i class="feather-credit-card me-1"></i> Expense Reports
                            </a>
                        </li>
                    @endif
                    @if ($canAssetReports)
                        <li class="nav-item flex-fill border-top">
                            <a class="nav-link" data-bs-toggle="tab" href="#assetReportTab">
                                <i class="feather-hard-drive me-1"></i> Asset Reports
                            </a>
                        </li>
                    @endif
                    @if ($canPayrollReports)
                        <li class="nav-item flex-fill border-top">
                            <a class="nav-link" data-bs-toggle="tab" href="#payrollReportTab">
                                <i class="feather-dollar-sign me-1"></i> Payroll Reports
                            </a>
                        </li>
                    @endif
                    @if ($canLeaveReports)
                        <li class="nav-item flex-fill border-top">
                            <a class="nav-link" data-bs-toggle="tab" href="#leaveReportTab">
                                <i class="feather-briefcase me-1"></i> Leave Reports
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="tab-content">
            {{-- ==================== ATTENDANCE REPORT TAB ==================== --}}
            @if ($canAttendanceReports)
            <div class="tab-pane fade show active" id="attendanceReportTab">
                <div class="report-cards-grid">
                    <!-- Overall Attendance Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-calendar"></i>
                        </div>
                        <h6 class="card-title">Overall Attendance</h6>
                        <p class="card-description">Monthly summaries with day-by-day status, total present, absent, leave & weekoff counts.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Monthly</span>
                            <a href="{{ route('report.attendance.overall.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>

                    <!-- Daywise Attendance Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-clock"></i>
                        </div>
                        <h6 class="card-title">Daywise Attendance</h6>
                        <p class="card-description">Comprehensive day-by-day punch details including clock-in/out, working hours & location data.</p>
                        <div class="card-footer">
                            <span class="badge badge-success">Daily</span>
                            <a href="{{ route('report.attendance.day.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>

                    <!-- Clock In/Out Log (every punch) -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-log-in"></i>
                        </div>
                        <h6 class="card-title">Clock In/Out Log</h6>
                        <p class="card-description">Every clock-in and clock-out (multiple per day) with time, direction, location, office radius, distance, source & device.</p>
                        <div class="card-footer">
                            <span class="badge badge-success">Punches</span>
                            <a href="{{ route('report.attendance.punches.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>

                    <!-- Working Hours Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-trending-up"></i>
                        </div>
                        <h6 class="card-title">Working Hours</h6>
                        <p class="card-description">Analyze total actual hours worked by employees with detailed daily hour breakdown.</p>
                        <div class="card-footer">
                            <span class="badge badge-info">Hours</span>
                            <a href="{{ route('report.attendance.hourly.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>

                    <!-- Detailed Attendance Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-file-text"></i>
                        </div>
                        <h6 class="card-title">Detailed Report</h6>
                        <p class="card-description">In-depth attendance tracker with complete status, shifts & overtime details.</p>
                        <div class="card-footer">
                            <span class="badge badge-warning">Detailed</span>
                            <a href="{{ route('report.attendance.detail.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>

                    <!-- Monthly Summary Attendance Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-bar-chart-2"></i>
                        </div>
                        <h6 class="card-title">Monthly Summary</h6>
                        <p class="card-description">Complete monthly attendance summary with status, shifts & overtime breakdown.</p>
                        <div class="card-footer">
                            <span class="badge badge-purple">Summary</span>
                            <a href="{{ route('report.attendance.summary.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>

                    @if ($canOvertimeReport)
                    <!-- Overtime Report (Monthly) -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-clock"></i>
                        </div>
                        <h6 class="card-title">Overtime Hours</h6>
                        <p class="card-description">Per-employee requested, approved & rejected overtime hours with an estimated payout cost for the month.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Overtime</span>
                            <a href="{{ route('report.overtime.monthly.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    @endif

                    <div class="report-card coming-soon-card">
                        <!--<div class="coming-soon-overlay">Coming Soon</div>-->
                        <div class="card-icon primary">
                            <i class="feather-clock"></i>
                        </div>
                        <h6 class="card-title">Attendance Location Wise Report</h6>
                        <p class="card-description">View attendance per attendance location with present, absent, leave, holiday and week-off counts for any date range.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Location</span>
                            <a href="{{route('report.attendance.branch-wise')}}" class="btn-generate" >
                                <i class="feather-clock"></i> Generate
                            </a>
                        </div>
                    </div>

                    @if ($canShiftReport)
                    <!-- Shift Report (Monthly) -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-layers"></i>
                        </div>
                        <h6 class="card-title">Shift Report</h6>
                        <p class="card-description">Monthly matrix of which employee is on which shift on which date, with week-offs marked and a shift/employee/department filter.</p>
                        <div class="card-footer">
                            <span class="badge badge-info">Monthly</span>
                            <a href="{{ route('report.attendance.shift-monthly.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    @endif

                    @if ($reportFeatures->enabledForCurrentTenant('custom_shift'))
                    <!-- Shift Change Log -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-git-commit"></i>
                        </div>
                        <h6 class="card-title">Shift Change Log</h6>
                        <p class="card-description">Every change to an employee's shift — from and to shift, roster edit / swap / request / rotation, who changed it, when and why.</p>
                        <div class="card-footer">
                            <span class="badge badge-info">Audit</span>
                            <a href="{{ route('report.attendance.shift-changes.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>

                    <!-- Shift Requests Register -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-shuffle"></i>
                        </div>
                        <h6 class="card-title">Shift Requests Register</h6>
                        <p class="card-description">Every shift swap and change request with its shifts, status, who decided it and how long the decision took.</p>
                        <div class="card-footer">
                            <span class="badge badge-info">Requests</span>
                            <a href="{{ route('report.attendance.shift-requests.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- ==================== PROJECT REPORT TAB ==================== --}}
            @if ($canProjectReports)
            <div class="tab-pane fade" id="projectReportTab">
                <div class="report-cards-grid">
                    <!-- Project Summary Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-briefcase"></i>
                        </div>
                        <h6 class="card-title">Project Summary</h6>
                        <p class="card-description">Every project with manager, status, priority, team size, task breakdown, progress % and budget vs. spent.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Projects</span>
                            <a href="{{ route('report.project.summary.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Project Progress Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-trending-up"></i>
                        </div>
                        <h6 class="card-title">Project Progress</h6>
                        <p class="card-description">Progress %, task completion breakdown, days remaining/overdue, and the latest posted update per project.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Projects</span>
                            <a href="{{ route('report.project.progress.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Project Task & Employee Performance Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-users"></i>
                        </div>
                        <h6 class="card-title">Project Task &amp; Employee Performance</h6>
                        <p class="card-description">Per-employee task workload and completion rate, scoped to a project, department, priority or status.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Projects</span>
                            <a href="{{ route('report.project.task-performance.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Project Timeline / Overdue Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-calendar"></i>
                        </div>
                        <h6 class="card-title">Project Timeline / Overdue</h6>
                        <p class="card-description">Projects ordered by deadline, overdue flags, days remaining/overdue, and each project's next upcoming milestone.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Projects</span>
                            <a href="{{ route('report.project.timeline.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ==================== TASK REPORT TAB ==================== --}}
            @if ($canTaskReports)
            <div class="tab-pane fade" id="taskReportTab">
                <div class="report-cards-grid">
                    <!-- Task & Project Overview Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-check-square"></i>
                        </div>
                        <h6 class="card-title">Task & Project Overview</h6>
                        <p class="card-description">Completion rate, overdue count, per-employee workload, and active project progress for the month.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Tasks</span>
                            <a href="{{ route('report.task-project.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Day-wise Task Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-calendar"></i>
                        </div>
                        <h6 class="card-title">Day-wise Task</h6>
                        <p class="card-description">Task counts broken down by status for every day in the selected date range.</p>
                        <div class="card-footer">
                            <span class="badge badge-success">Daily</span>
                            <a href="{{ route('report.task.day-wise') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Employee Monthly Task Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-bar-chart-2"></i>
                        </div>
                        <h6 class="card-title">Employee Monthly Task</h6>
                        <p class="card-description">Per-employee task counts by status for the selected month, with company-wide totals.</p>
                        <div class="card-footer">
                            <span class="badge badge-info">Monthly</span>
                            <a href="{{ route('report.task.employee-monthly') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Employee Date-wise Task Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-user-check"></i>
                        </div>
                        <h6 class="card-title">Employee Date-wise Task</h6>
                        <p class="card-description">Per-employee task counts by status for a single selected date.</p>
                        <div class="card-footer">
                            <span class="badge badge-warning">Daily</span>
                            <a href="{{ route('report.task.employee-date-wise') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Monthly Task Detail Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-file-text"></i>
                        </div>
                        <h6 class="card-title">Monthly Task Detail</h6>
                        <p class="card-description">Full task-by-task breakdown for the month, including assignees, updates, and approval history.</p>
                        <div class="card-footer">
                            <span class="badge badge-purple">Detailed</span>
                            <a href="{{ route('report.task.monthly-task-detail') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ==================== EXPENSE REPORTS TAB ==================== --}}
            @if ($canExpenseReports)
                <div class="tab-pane fade" id="expenseReportTab">
                    <div class="report-cards-grid">
                        <!-- Payment Register -->
                        <div class="report-card">
                            <div class="card-icon primary">
                                <i class="feather-list"></i>
                            </div>
                            <h6 class="card-title">Payment Register</h6>
                            <p class="card-description">Every expense payment in a date range — voucher, employee, expense, mode, reference and status, with voided payments shown.</p>
                            <div class="card-footer">
                                <span class="badge badge-primary">Payments</span>
                                <a href="{{ route('expense.reports.show', 'register') }}" class="btn-generate">
                                    <i class="feather-arrow-right"></i> Generate
                                </a>
                            </div>
                        </div>
                        <!-- Expense Summary -->
                        <div class="report-card">
                            <div class="card-icon primary">
                                <i class="feather-bar-chart-2"></i>
                            </div>
                            <h6 class="card-title">Expense Summary</h6>
                            <p class="card-description">Claims, submitted, approved, pending and rejected amounts grouped by employee, category or project for a period.</p>
                            <div class="card-footer">
                                <span class="badge badge-purple">Summary</span>
                                <a href="{{ route('expense.reports.show', 'summary') }}" class="btn-generate">
                                    <i class="feather-arrow-right"></i> Generate
                                </a>
                            </div>
                        </div>
                        <!-- Outstanding Advances (ageing) -->
                        <div class="report-card">
                            <div class="card-icon primary">
                                <i class="feather-clock"></i>
                            </div>
                            <h6 class="card-title">Outstanding Advances</h6>
                            <p class="card-description">Unspent advance per employee aged into 0-30, 31-60, 61-90 and 90+ day buckets, with the oldest balance highlighted.</p>
                            <div class="card-footer">
                                <span class="badge badge-warning">Ageing</span>
                                <a href="{{ route('expense.reports.show', 'ageing') }}" class="btn-generate">
                                    <i class="feather-arrow-right"></i> Generate
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ==================== ASSET REPORTS TAB ==================== --}}
            @if ($canAssetReports)
            <div class="tab-pane fade" id="assetReportTab">
                <div class="report-cards-grid">
                    <!-- Asset Register -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-hard-drive"></i>
                        </div>
                        <h6 class="card-title">Asset Register</h6>
                        <p class="card-description">Every company asset with category, status, branch, current assignee, purchase date and warranty end — filter by category, status or branch and export to CSV.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Register</span>
                            <a href="{{ route('report.asset.register.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Employee-wise Assets -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-users"></i>
                        </div>
                        <h6 class="card-title">Employee-wise Assets</h6>
                        <p class="card-description">Each employee who holds company assets, with the assets assigned to them, their category and assignment date.</p>
                        <div class="card-footer">
                            <span class="badge badge-success">Employees</span>
                            <a href="{{ route('report.asset.employee-wise.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Asset Summary -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-pie-chart"></i>
                        </div>
                        <h6 class="card-title">Asset Summary</h6>
                        <p class="card-description">Total assets broken down by status and by category, at a glance.</p>
                        <div class="card-footer">
                            <span class="badge badge-purple">Summary</span>
                            <a href="{{ route('report.asset.summary.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Warranty Expiry -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-shield"></i>
                        </div>
                        <h6 class="card-title">Warranty Expiry</h6>
                        <p class="card-description">Assets whose warranty has already ended or ends within the next 90 days (adjustable), oldest first, so renewals can be planned.</p>
                        <div class="card-footer">
                            <span class="badge badge-warning">Warranty</span>
                            <a href="{{ route('report.asset.warranty-expiry.index') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ==================== PAYROLL REPORTS TAB ==================== --}}
            @if ($canPayrollReports)
                <div class="tab-pane fade" id="payrollReportTab">
                    <div class="report-cards-grid">
                        <!-- Monthly Payroll Report -->
                        <div class="report-card">
                            <div class="card-icon primary">
                                <i class="feather-calendar"></i>
                            </div>
                            <h6 class="card-title">Monthly Payroll Report</h6>
                            <p class="card-description">Every employee's gross, deductions, net payable and payment status for a selected month, filterable by department.</p>
                            <div class="card-footer">
                                <span class="badge badge-primary">Monthly</span>
                                <a href="{{ route('report.payroll.show', 'monthly') }}" class="btn-generate">
                                    <i class="feather-arrow-right"></i> Generate
                                </a>
                            </div>
                        </div>
                        <!-- Employee Payroll Structure Report -->
                        <div class="report-card">
                            <div class="card-icon primary">
                                <i class="feather-layers"></i>
                            </div>
                            <h6 class="card-title">Employee Payroll Structure</h6>
                            <p class="card-description">Each employee's current salary structure, annual CTC, effective date and approval status.</p>
                            <div class="card-footer">
                                <span class="badge badge-success">Structure</span>
                                <a href="{{ route('report.payroll.show', 'structure') }}" class="btn-generate">
                                    <i class="feather-arrow-right"></i> Generate
                                </a>
                            </div>
                        </div>
                        <!-- Loan Report -->
                        <div class="report-card">
                            <div class="card-icon primary">
                                <i class="feather-credit-card"></i>
                            </div>
                            <h6 class="card-title">Loan Report</h6>
                            <p class="card-description">Every employee loan with category, amount, outstanding balance and status, filterable by department and status.</p>
                            <div class="card-footer">
                                <span class="badge badge-warning">Loans</span>
                                <a href="{{ route('report.payroll.show', 'loan') }}" class="btn-generate">
                                    <i class="feather-arrow-right"></i> Generate
                                </a>
                            </div>
                        </div>
                        <!-- Payroll Summary Report -->
                        <div class="report-card">
                            <div class="card-icon primary">
                                <i class="feather-pie-chart"></i>
                            </div>
                            <h6 class="card-title">Payroll Summary</h6>
                            <p class="card-description">Department-wise rollup of employees, gross, deductions and net payable for a selected month, with a paid/total count.</p>
                            <div class="card-footer">
                                <span class="badge badge-purple">Summary</span>
                                <a href="{{ route('report.payroll.show', 'summary') }}" class="btn-generate">
                                    <i class="feather-arrow-right"></i> Generate
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ==================== LEAVE REPORTS TAB ==================== --}}
            @if ($canLeaveReports)
            <div class="tab-pane fade" id="leaveReportTab">
                <div class="report-cards-grid">
                    <!-- Leave Balance Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-battery-charging"></i>
                        </div>
                        <h6 class="card-title">Leave Balance Report</h6>
                        <p class="card-description">Every employee's remaining balance per leave type, filterable by department and leave type.</p>
                        <div class="card-footer">
                            <span class="badge badge-primary">Balance</span>
                            <a href="{{ route('report.leave.show', 'balance') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Leave Report (register) -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-file-text"></i>
                        </div>
                        <h6 class="card-title">Leave Report</h6>
                        <p class="card-description">Every leave application in a date range with type, dates, days and status.</p>
                        <div class="card-footer">
                            <span class="badge badge-success">Register</span>
                            <a href="{{ route('report.leave.show', 'register') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    <!-- Leave Summary Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-bar-chart-2"></i>
                        </div>
                        <h6 class="card-title">Leave Summary</h6>
                        <p class="card-description">Applications and days taken grouped by leave type, with approved/pending/cancelled counts for a date range.</p>
                        <div class="card-footer">
                            <span class="badge badge-purple">Summary</span>
                            <a href="{{ route('report.leave.show', 'summary') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    @if ($canRegularizationReport)
                    <!-- Regularization Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-edit-3"></i>
                        </div>
                        <h6 class="card-title">Regularization Report</h6>
                        <p class="card-description">Attendance regularization requests in a date range with in/out time, reason and status.</p>
                        <div class="card-footer">
                            <span class="badge badge-warning">Regularization</span>
                            <a href="{{ route('report.leave.show', 'regularization') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    @endif
                    @if ($canWfhTravelReport)
                    <!-- WFH & Travel Report -->
                    <div class="report-card">
                        <div class="card-icon primary">
                            <i class="feather-map-pin"></i>
                        </div>
                        <h6 class="card-title">WFH &amp; Travel Report</h6>
                        <p class="card-description">Work-from-home and travel requests in a date range, filterable by type and status.</p>
                        <div class="card-footer">
                            <span class="badge badge-info">Requests</span>
                            <a href="{{ route('report.leave.show', 'wfh-travel') }}" class="btn-generate">
                                <i class="feather-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Info Alert - Compact -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-info" style="border-radius: 10px; border: 1px solid #dbeafe; background: #eff6ff;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-info" style="font-size: 14px; color: var(--icon-color, #0D6EFD);"></i>
                        <div>
                            <h6 class="mb-0" style="font-weight: 600; color: #0D6EFD; font-size: 10.5px;">All Reports Available</h6>
                            <p class="mb-0" style="color: #3b82f6; font-size: 10px;">
                                Generate attendance, project, task, expense, asset, payroll and leave reports with real-time data. Reports can be exported as CSV for further analysis.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "extendedTimeOut": "1000",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Open the tab named in the URL hash (or the one last used) so coming back from a report page
        // lands on the tab you were in, and remember the choice.
        (function() {
            const KEY = 'reportsLastTab';
            const tabs = document.querySelectorAll('#reportTab a[data-bs-toggle="tab"]');
            const known = h => Array.from(tabs).some(a => a.getAttribute('href') === h);
            let target = null;
            try {
                if (known(location.hash)) target = location.hash;
                else if (known(localStorage.getItem(KEY))) target = localStorage.getItem(KEY);
            } catch (e) {}
            // Nothing remembered: open the first tab this company has (Attendance may be off).
            if (!target && tabs.length) target = tabs[0].getAttribute('href');
            if (target && window.bootstrap) {
                const link = document.querySelector('#reportTab a[href="' + target + '"]');
                if (link) bootstrap.Tab.getOrCreateInstance(link).show();
            }
            tabs.forEach(a => a.addEventListener('shown.bs.tab', function() {
                const h = a.getAttribute('href');
                try { localStorage.setItem(KEY, h); } catch (e) {}
                if (history.replaceState) history.replaceState(null, '', h);
            }));
        })();

        // Animate cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.report-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(15px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 80 * (index + 1));
            });
        });
    </script>
@endsection