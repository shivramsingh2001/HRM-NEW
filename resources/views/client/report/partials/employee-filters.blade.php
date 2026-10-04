{{--
    Branch / Department / Designation / Attendance Location dropdowns for the
    attendance reports' filter row (applied server-side by the
    App\Http\Controllers\Concerns\FiltersReportEmployees trait). Each one submits
    the form on change. Branch only shows when the plan has the Branches module.

    @include('client.report.partials.employee-filters', [
        'selectClass' => 'form-control-sm-custom',   // the page's own input class
        'deptParam'   => 'department',              // or 'department_id' (keep the page's existing name)
        'desigParam'  => 'designation',             // or 'designation_id'
        'except'      => ['location'],              // optional: skip one (e.g. a page already scoped to a location)
    ])
--}}
@php
    $efTenant = session('tenant_id');
    $efClass = $selectClass ?? 'form-control-sm-custom';
    $efDept = $deptParam ?? 'department';
    $efDesig = $desigParam ?? 'designation';
    $efExcept = $except ?? [];
    $efCur = fn ($a, $b = null) => (string) request()->query($a, $b ? request()->query($b) : null);

    $efDepartments = DB::table('departments')->where('tenant_id', $efTenant)->where('status', 1)->orderBy('name')->get(['id', 'name']);
    $efDesignations = DB::table('designations')->where('tenant_id', $efTenant)->where('status', 1)->orderBy('name')->get(['id', 'name']);
    $efBranches = DB::table('company_branches')->where('tenant_id', $efTenant)->where('status', 1)->orderBy('name')->get(['id', 'name']);
    $efLocations = DB::table('attendance_locations')->where('tenant_id', $efTenant)->where('status', 1)->orderBy('name')->get(['id', 'name']);

    $efDeptVal = $efCur($efDept, $efDept === 'department' ? 'department_id' : 'department');
    $efDesigVal = $efCur($efDesig, $efDesig === 'designation' ? 'designation_id' : 'designation');
@endphp

@if (! in_array('branch', $efExcept, true))
    @feature('branches')
    <div class="filter-item">
        <select name="branch_id" class="{{ $efClass }}" onchange="this.form.submit()" aria-label="Branch">
            <option value="">-- All Branches --</option>
            @foreach ($efBranches as $o)
                <option value="{{ $o->id }}" @selected($efCur('branch_id') === (string) $o->id)>{{ $o->name }}</option>
            @endforeach
        </select>
    </div>
    @endfeature
@endif

@if (! in_array('department', $efExcept, true))
    <div class="filter-item">
        <select name="{{ $efDept }}" class="{{ $efClass }}" onchange="this.form.submit()" aria-label="Department">
            <option value="">-- All Departments --</option>
            @foreach ($efDepartments as $o)
                <option value="{{ $o->id }}" @selected($efDeptVal === (string) $o->id)>{{ $o->name }}</option>
            @endforeach
        </select>
    </div>
@endif

@if (! in_array('designation', $efExcept, true))
    <div class="filter-item">
        <select name="{{ $efDesig }}" class="{{ $efClass }}" onchange="this.form.submit()" aria-label="Designation">
            <option value="">-- All Designations --</option>
            @foreach ($efDesignations as $o)
                <option value="{{ $o->id }}" @selected($efDesigVal === (string) $o->id)>{{ $o->name }}</option>
            @endforeach
        </select>
    </div>
@endif

@if (! in_array('location', $efExcept, true))
    <div class="filter-item">
        <select name="location_id" class="{{ $efClass }}" onchange="this.form.submit()" aria-label="Attendance Location">
            <option value="">-- All Attendance Locations --</option>
            <option value="0" @selected($efCur('location_id') === '0')>Any location</option>
            @foreach ($efLocations as $o)
                <option value="{{ $o->id }}" @selected($efCur('location_id') === (string) $o->id)>{{ $o->name }}</option>
            @endforeach
        </select>
    </div>
@endif
