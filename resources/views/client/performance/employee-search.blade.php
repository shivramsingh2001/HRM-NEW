@extends('client.layout.master')

@section('style')
    <style>
        .search-filters {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .employee-card {
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .employee-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .employee-info-card {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .employee-avatar-md {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }
        
        .performance-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .perf-excellent { background: #10b981; }
        .perf-good { background: #3b82f6; }
        .perf-average { background: #f59e0b; }
        .perf-poor { background: #ef4444; }
        
        .search-input {
            width: 100%;
            height: 42px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0 1rem;
            font-size: 14px;
        }
        
        .result-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }
        
        @media (max-width: 768px) {
            .employee-card { flex-direction: column; gap: 1rem; text-align: center; }
            .employee-info-card { flex-direction: column; }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Employee Performance Search</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('performance.team') }}">Performance</a></li>
                <li class="breadcrumb-item active">Search</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">

        <!-- Search Filters -->
        <div class="search-filters">
            <form method="GET" action="{{ route('performance.search') }}" id="searchForm">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small text-muted">Search Employee</label>
                        <input type="text" name="search" class="search-input" 
                               placeholder="Name, Employee ID, or Email..." 
                               value="{{ $search ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Department</label>
                        <select name="department_id" class="filter-select">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ ($departmentId ?? '') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="feather-search me-1"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Search Results -->
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title mb-0">Search Results</h5>
            </div>
            <div class="card-body">
                @if(isset($employees) && $employees->count() > 0)
                    <div class="result-stats">
                        <span class="text-muted">
                            Found {{ $employees->total() }} employee(s)
                        </span>
                        <a href="{{ route('performance.team') }}" class="btn btn-sm btn-light">
                            <i class="feather-users"></i> View Team Report
                        </a>
                    </div>
                    
                    @foreach($employees as $employee)
                    <div class="employee-card">
                        <div class="employee-info-card">
                            <div class="employee-avatar-md">
                                {{ strtoupper(substr($employee->name, 0, 2)) }}
                            </div>
                            <div>
                                <div class="fw-semibold fs-16">{{ $employee->name }}</div>
                                <div class="text-muted small">
                                    {{ $employee->jobDetails?->Designation?->name ?? 'No Designation' }} | 
                                    {{ $employee->jobDetails?->Department?->name ?? 'No Department' }}
                                </div>
                                <div class="text-muted small">
                                    <i class="feather-mail"></i> {{ $employee->email }} | 
                                    <i class="feather-user"></i> ID: {{ $employee->employee_id ?? 'N/A' }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            @php
                                $score = $employee->latest_score;
                                $perfClass = 'perf-average';
                                if ($score && $score->overall_score >= 80) $perfClass = 'perf-excellent';
                                elseif ($score && $score->overall_score >= 60) $perfClass = 'perf-good';
                                elseif ($score && $score->overall_score < 40) $perfClass = 'perf-poor';
                            @endphp
                            <div class="text-center">
                                <div class="performance-indicator {{ $perfClass }}"></div>
                                <div class="small text-muted">Performance</div>
                                <div class="fw-bold">{{ $score->overall_score ?? 'N/A' }}%</div>
                            </div>
                            <div class="text-center">
                                <div class="small text-muted">Grade</div>
                                <div class="fs-20 fw-bold grade-{{ $score->grade ?? 'C' }}" style="color: {{ $score->grade == 'A' ? '#10b981' : ($score->grade == 'B' ? '#3b82f6' : ($score->grade == 'C' ? '#f59e0b' : '#ef4444')) }}">
                                    {{ $score->grade ?? 'N/A' }}
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="small text-muted">Attendance</div>
                                <div class="fw-bold">{{ $score->attendance_score ?? 'N/A' }}%</div>
                            </div>
                            <a href="{{ route('performance.individual', $employee->id) }}" class="btn btn-primary btn-sm">
                                <i class="feather-bar-chart-2"></i> View Report
                            </a>
                        </div>
                    </div>
                    @endforeach
                    
                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $employees->appends(request()->query())->links() }}
                    </div>
                    
                @else
                    <div class="text-center py-5">
                        <i class="feather-users fs-48 text-muted"></i>
                        <h5 class="mt-3">No employees found</h5>
                        <p class="text-muted">Try adjusting your search criteria</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Auto-submit on department change
            $('select[name="department_id"]').on('change', function() {
                $('#searchForm').submit();
            });
        });
    </script>
@endsection