@extends('client.layout.master')

@section('style')
    <style>
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
            background: linear-gradient(90deg, #1e3a8a, #2563eb);
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
            color: #1e3a8a;
        }

        .card-icon.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(16, 185, 129, 0.05));
            color: #1e3a8a;
        }

        .card-icon.info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(59, 130, 246, 0.05));
            color: #3b82f6;
        }

        .card-icon.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.05));
            color: #2563eb;
        }

        .card-icon.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(239, 68, 68, 0.05));
            color: #475569;
        }

        .card-icon.purple {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.12), rgba(139, 92, 246, 0.05));
            color: #2563eb;
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
            padding-top: 8px;
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
            background: #e3edfe;
            color: #1e3a8a;
        }

        .badge-success {
            background: #e3edfe;
            color: #1e3a8a;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-warning {
            background: #bfd3f7;
            color: #1e3a8a;
        }

        .badge-danger {
            background: #e2e8f0;
            color: #475569;
        }

        .badge-purple {
            background: #e3edfe;
            color: #16295e;
        }

        .report-card .btn-generate {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 9.5px;
            font-weight: 600;
            background: #1e3a8a;
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
            background: #16295e;
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
            background: #e3edfe;
            color: #2563eb;
        }

        .quick-stat-icon.blue {
            background: #dbeafe;
            color: #3b82f6;
        }

        .quick-stat-icon.green {
            background: #e3edfe;
            color: #1e3a8a;
        }

        .quick-stat-icon.orange {
            background: #bfd3f7;
            color: #2563eb;
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
     <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Attendance Reports</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Attendance Reports</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <span class="badge badge-info-custom">
                    <i class="feather-calendar me-1"></i> {{ now()->format('F Y') }}
                </span>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Report Cards Grid -->
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
                <div class="card-icon success">
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

            <!-- Working Hours Report -->
            <div class="report-card">
                <div class="card-icon info">
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
                <div class="card-icon warning">
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
                <div class="card-icon purple">
                    <i class="feather-bar-chart-2"></i>
                </div>
                <h6 class="card-title">Monthly Summary</h6>
                <p class="card-description">Complete monthly attendance summary with status, shifts & overtime breakdown.</p>
                <div class="card-footer">
                    <span class="badge badge-purple">Summary</span>
                    <a href="{{ route('team.attendance-summary') }}" class="btn-generate">
                        <i class="feather-arrow-right"></i> Generate
                    </a>
                </div>
            </div>

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
            <!-- Task & Project Report -->
            <div class="report-card">
                <div class="card-icon primary">
                    <i class="feather-check-square"></i>
                </div>
                <h6 class="card-title">Task & Project</h6>
                <p class="card-description">Completion rate, overdue count, per-employee workload, and active project progress for the month.</p>
                <div class="card-footer">
                    <span class="badge badge-primary">Tasks</span>
                    <a href="{{ route('report.task-project.index') }}" class="btn-generate">
                        <i class="feather-arrow-right"></i> Generate
                    </a>
                </div>
            </div>
              <div class="report-card coming-soon-card">
                <!--<div class="coming-soon-overlay">Coming Soon</div>-->
                <div class="card-icon primary">
                    <i class="feather-clock"></i>
                </div>
                <h6 class="card-title">Branch Wise Attendance Report</h6>
                <p class="card-description">View attendance summary by branch with present, absent, leave, and holiday counts for any selected date.</p>
                <div class="card-footer">
                    <span class="badge badge-primary">Branch</span>
                    <a href="{{route('report.attendance.branch-wise')}}" class="btn-generate" >
                        <i class="feather-clock"></i> Generate
                    </a>
                </div>
            </div>
        </div>

        <!-- Info Alert - Compact -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-info" style="border-radius: 10px; border: 1px solid #dbeafe; background: #eff6ff;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-info" style="font-size: 14px; color: #3b82f6;"></i>
                        <div>
                            <h6 class="mb-0" style="font-weight: 600; color: #1e40af; font-size: 10.5px;">All Reports Available</h6>
                            <p class="mb-0" style="color: #3b82f6; font-size: 10px;">
                                Generate attendance reports with real-time data. All reports can be exported as CSV for further analysis.
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