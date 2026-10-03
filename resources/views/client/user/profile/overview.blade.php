{{-- Employee 360 — overview KPIs (EmployeeProfileController::tabOverview) --}}
<div class="p360-kpis">
    <div class="p360-kpi">
        <div class="v">{{ $presentDays }}</div>
        <div class="l">Present · {{ $month }}</div>
    </div>
    <div class="p360-kpi">
        <div class="v">{{ $lateDays }}</div>
        <div class="l">Late days</div>
    </div>
    <div class="p360-kpi">
        <div class="v">{{ rtrim(rtrim(number_format($leaveBalance, 2), '0'), '.') }}</div>
        <div class="l">Leave balance</div>
    </div>
    <div class="p360-kpi">
        <div class="v">{{ $pending['leaves'] + $pending['regularizations'] + $pending['expenses'] }}</div>
        <div class="l" title="{{ $pending['leaves'] }} leave · {{ $pending['regularizations'] }} regularization · {{ $pending['expenses'] }} expense">Pending requests</div>
    </div>
    <div class="p360-kpi">
        <div class="v">{{ $openTasks }}</div>
        <div class="l">Open tasks</div>
    </div>
    <div class="p360-kpi">
        <div class="v">{{ $assets }}</div>
        <div class="l">Assets held</div>
    </div>
</div>
