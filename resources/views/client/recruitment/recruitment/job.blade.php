{{-- resources/views/public/jobs/index.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Career Opportunities | {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
        }

        /* Header Section */
        .career-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .career-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
        }

        .career-hero h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
            position: relative;
        }

        .career-hero p {
            font-size: 18px;
            opacity: 0.95;
            position: relative;
        }

        /* Search Section */
        .search-section {
            max-width: 1200px;
            margin: -30px auto 0;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .search-card {
            background: white;
            border-radius: 16px;
            padding: 24px 30px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
        }

        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-group {
            flex: 1;
            min-width: 200px;
        }

        .search-group input, .search-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-group input:focus, .search-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            padding: 12px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .search-btn:hover {
            transform: translateY(-2px);
        }

        /* Stats Section */
        .stats-section {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            margin-top: 5px;
        }

        /* Jobs Grid */
        .jobs-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 20px 60px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .section-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }

        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }

        .job-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .job-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .job-company {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 15px 0;
        }

        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #475569;
            background: #f8fafc;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .job-meta-item i {
            font-size: 11px;
            color: #667eea;
        }

        .job-description {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin: 12px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .job-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eef2f6;
        }

        .job-type {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .job-type-full_time { background: #dbeafe; color: #1e40af; }
        .job-type-part_time { background: #fef3c7; color: #92400e; }
        .job-type-contract { background: #f1f5f9; color: #475569; }
        .job-type-internship { background: #e0e7ff; color: #3730a3; }
        .job-type-temporary { background: #e5e7eb; color: #4b5563; }

        .apply-btn {
            padding: 6px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .apply-btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        /* Pagination */
        .pagination-container {
            margin-top: 40px;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            gap: 8px;
            list-style: none;
        }

        .pagination li a, .pagination li span {
            display: inline-block;
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
        }

        .pagination li.active span {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .career-hero h1 { font-size: 32px; }
            .jobs-grid { grid-template-columns: 1fr; }
            .search-form { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="career-hero">
        <h1>Join Our Team</h1>
        <p>Build your career with us and make a difference</p>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-card">
            <form action="{{ route('public.jobs.list') }}" method="GET" class="search-form">
                <div class="search-group">
                    <input type="text" name="search" placeholder="Search by job title, keyword..." value="{{ request('search') }}">
                </div>
                <div class="search-group">
                    <input type="text" name="location" placeholder="Location (City, State)" value="{{ request('location') }}">
                </div>
                <div class="search-group">
                    <select name="employment_type">
                        <option value="">All Job Types</option>
                        <option value="full_time" {{ request('employment_type') == 'full_time' ? 'selected' : '' }}>Full Time</option>
                        <option value="part_time" {{ request('employment_type') == 'part_time' ? 'selected' : '' }}>Part Time</option>
                        <option value="contract" {{ request('employment_type') == 'contract' ? 'selected' : '' }}>Contract</option>
                        <option value="internship" {{ request('employment_type') == 'internship' ? 'selected' : '' }}>Internship</option>
                        <option value="temporary" {{ request('employment_type') == 'temporary' ? 'selected' : '' }}>Temporary</option>
                    </select>
                </div>
                <button type="submit" class="search-btn">Find Jobs</button>
            </form>
        </div>
    </div>

    <!-- Statistics Section -->
    {{-- @if(isset($stats))
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">{{ $stats['total'] ?? 0 }}</div>
                <div class="stat-label">Open Positions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $stats['departments'] ?? 0 }}</div>
                <div class="stat-label">Departments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $stats['locations'] ?? 0 }}</div>
                <div class="stat-label">Locations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $stats['hired'] ?? 0 }}</div>
                <div class="stat-label">People Hired</div>
            </div>
        </div>
    </div>
    @endif --}}

    <!-- Jobs Section -->
    <div class="jobs-section">
        <div class="section-header">
            <h2><i class="fas fa-briefcase"></i> Current Openings</h2>
            <span style="font-size: 13px; color: #64748b;">{{ $jobs->total() }} positions available</span>
        </div>

        @if($jobs->count() > 0)
        <div class="jobs-grid">
            @foreach($jobs as $job)
            <div class="job-card">
                <h3 class="job-title">{{ $job->title }}</h3>
                <div class="job-company">
                    <i class="fas fa-building"></i> {{ $job->department->name ?? config('app.name') }}
                </div>
                <div class="job-meta">
                    <span class="job-meta-item"><i class="fas fa-map-marker-alt"></i> {{ $job->location ?? 'Remote' }}</span>
                    <span class="job-meta-item"><i class="fas fa-calendar-alt"></i> Posted {{ $job->created_at->diffForHumans() }}</span>
                    @if($job->experience_required)
                    <span class="job-meta-item"><i class="fas fa-chart-line"></i> {{ $job->experience_required }}</span>
                    @endif
                </div>
                <div class="job-description">
                    {{ Str::limit(strip_tags($job->description), 120) }}
                </div>
                <div class="job-footer">
                    <span class="job-type job-type-{{ $job->employment_type }}">
                        {{ ucfirst(str_replace('_', ' ', $job->employment_type)) }}
                    </span>
                    <a href="{{ route('public.jobs.apply.form', $job->id) }}" class="apply-btn">
                        Apply Now <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        <div class="pagination-container">
            {{ $jobs->appends(request()->query())->links('pagination::bootstrap-4') }}
        </div>
        @else
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3>No Jobs Found</h3>
            <p>We couldn't find any jobs matching your criteria. Please try different keywords or check back later.</p>
            <a href="{{ route('public.jobs.list') }}" class="btn btn-primary mt-3">Clear Filters</a>
        </div>
        @endif
    </div>

    <!-- Footer -->
    <footer style="background: #1e293b; color: #94a3b8; padding: 40px 20px; text-align: center;">
        <p>&copy; {{ date('Y') }} Shurt Tech. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>