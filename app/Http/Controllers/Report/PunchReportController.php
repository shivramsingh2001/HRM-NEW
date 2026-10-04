<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use App\Models\CompanyBranch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clock In/Out (punch-level) report — one row per clock-in or clock-out, with
 * where it happened (address, lat/long, office geofence radius, distance,
 * verification), how (source, method, biometric terminal, phone device,
 * network) and the session it belongs to.
 *
 * Source of truth is `attendance_punches` (every multi-punch capture). Days
 * that have an `attendances` row but no active punch rows (single-punch
 * biometric, web/manual marking, pre-backfill gaps) are shown from the row's
 * clock_in / clock_out so the log never silently drops a day.
 *
 * `role:admin,hr,manager` (the whole Reports area); a manager only sees their
 * own reportees, same as the other attendance reports.
 */
class PunchReportController extends Controller
{
    use SanitizesCsv;

    private const PER_PAGE = 50;
    private const MAX_DAYS = 366;

    public function index(Request $request)
    {
        try {
            $f = $this->filters($request);
            $query = $this->query($f);

            $stats = DB::query()->fromSub($query, 's')->selectRaw(
                "COUNT(*) as total,
                 SUM(direction = 'in') as ins,
                 SUM(direction = 'out') as outs,
                 COUNT(DISTINCT user_id) as employees,
                 SUM(location_verification = 'out_of_bounds') as out_of_bounds"
            )->first();

            $rows = (clone $query)
                ->orderByDesc('r.punched_at')->orderByDesc('r.row_id')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            return view('client.report.attendance.punch-log', [
                'rows' => $rows,
                'stats' => $stats,
                'filters' => $f,
            ] + $this->options($f['tenant_id']));
        } catch (Exception $e) {
            Log::error('Punch report error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to load the clock in/out report.');
        }
    }

    public function export(Request $request)
    {
        try {
            $f = $this->filters($request);

            $handle = fopen('php://temp', 'w+');
            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeCsvRow($handle, [
                'Date', 'Time', 'Direction', 'Session', 'Employee ID', 'Employee', 'Department', 'Designation', 'Branch',
                'Attendance Location', 'Source', 'Method', 'Terminal', 'Address', 'Latitude', 'Longitude', 'Office location', 'Radius (m)',
                'Distance (m)', 'Location check', 'GPS accuracy (m)', 'Phone device', 'Network', 'Wi-Fi', 'IP', 'Battery %',
                'Regularized', 'Recorded by', 'Note',
            ]);

            foreach ($this->query($f)->orderBy('r.punched_at')->orderBy('r.row_id')->cursor() as $r) {
                $this->writeCsvRow($handle, [
                    $r->date, Carbon::parse($r->punched_at)->format('H:i:s'), strtoupper($r->direction), $r->session_seq,
                    $r->employee_code, $r->employee_name, $r->department_name, $r->designation_name, $r->branch_name,
                    \App\Models\AttendanceLocation::labelFor($r->office_branch, $r->assigned_location_name), self::sourceLabel($r->source), $r->method, $r->terminal_name, $r->address, $r->lat, $r->lng,
                    $r->location_name, $r->radius, $r->distance_meters, $r->location_verification, $r->accuracy_meters,
                    $r->device_id, $r->network_type, $r->wifi_ssid, $r->ip_address, $r->battery_percent,
                    $r->is_regularized ? 'Yes' : 'No', $r->actor_name, $r->reason,
                ]);
            }

            rewind($handle);
            $body = stream_get_contents($handle);
            fclose($handle);

            return response($body, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="clock-in-out-' . $f['start'] . '-to-' . $f['end'] . '.csv"',
            ]);
        } catch (Exception $e) {
            Log::error('Punch report export error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to export the clock in/out report.');
        }
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'mobile_app' => 'Mobile app',
            'biometric' => 'Biometric',
            'web' => 'Web',
            'manual' => 'Manual',
            'kiosk' => 'Kiosk',
            'api' => 'API',
            'backfill' => 'History',
            default => ucfirst((string) $source),
        };
    }

    /** Validated filters; dates default to the current month. */
    private function filters(Request $request): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        $start = $date($request->query('start_date')) ?? now()->startOfMonth()->toDateString();
        $end = $date($request->query('end_date')) ?? now()->endOfMonth()->toDateString();
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        if (Carbon::parse($start)->diffInDays(Carbon::parse($end)) > self::MAX_DAYS) {
            $end = Carbon::parse($start)->addDays(self::MAX_DAYS)->toDateString();
        }

        $int = fn ($k) => $request->filled($k) ? (int) $request->query($k) : null;

        return [
            'tenant_id' => (int) Auth::user()->tenant_id,
            'start' => $start,
            'end' => $end,
            'search' => trim((string) $request->query('search', '')),
            'branch_id' => $int('branch_id'),
            'user_id' => $int('user_id'),
            'department_id' => $int('department_id'),
            'designation_id' => $int('designation_id'),
            'location_id' => $int('location_id'), // assigned attendance location (user_job_details.office_branch)
            'direction' => in_array($request->query('direction'), ['in', 'out'], true) ? $request->query('direction') : null,
            'source' => in_array($request->query('source'), ['mobile_app', 'biometric', 'web', 'manual', 'kiosk', 'api', 'backfill'], true)
                ? $request->query('source') : null,
        ];
    }

    /** Punch rows UNION day rows without punches, joined to employee / org / location details, filters applied. */
    private function query(array $f): Builder
    {
        $tenantId = $f['tenant_id'];

        $punches = DB::table('attendance_punches as p')
            ->where('p.tenant_id', $tenantId)
            ->where('p.status', 'active')
            ->whereBetween('p.date', [$f['start'], $f['end']])
            ->select([
                'p.id as row_id', DB::raw("'punch' as row_type"), 'p.user_id', 'p.date', 'p.direction', 'p.punched_at',
                'p.source', 'p.method', 'p.biometric_device_id', 'p.lat', 'p.long as lng', 'p.address',
                'p.location_verification', 'p.attendance_location_id', 'p.distance_meters', 'p.accuracy_meters',
                'p.device_id', 'p.network_type', 'p.wifi_ssid', 'p.ip_address', 'p.battery_percent',
                'p.session_seq', 'p.is_regularized', 'p.actor_id', 'p.reason',
            ]);

        $noPunches = fn ($q) => $q->select(DB::raw(1))->from('attendance_punches as x')
            ->whereColumn('x.tenant_id', 'a.tenant_id')->whereColumn('x.user_id', 'a.user_id')
            ->whereColumn('x.date', 'a.date')->where('x.status', 'active');

        $daySource = "CASE a.attendance_type WHEN 'app' THEN 'mobile_app' WHEN 'manual' THEN 'manual'
            WHEN 'fingerprint' THEN 'biometric' WHEN 'face' THEN 'biometric' WHEN 'card' THEN 'biometric' ELSE 'web' END";

        $dayRow = function (string $dir) use ($tenantId, $f, $noPunches, $daySource) {
            $c = $dir === 'in'
                ? ['time' => 'clock_in', 'lat' => 'clock_in_lat', 'lng' => 'clock_in_long', 'addr' => 'clock_in_address', 'dist' => 'check_in_distance']
                : ['time' => 'clock_out', 'lat' => 'clock_out_lat', 'lng' => 'clock_out_long', 'addr' => 'clock_out_address', 'dist' => 'check_out_distance'];

            return DB::table('attendances as a')
                ->where('a.tenant_id', $tenantId)
                ->whereBetween('a.date', [$f['start'], $f['end']])
                ->whereNotNull("a.{$c['time']}")
                ->whereNotExists($noPunches)
                ->select([
                    'a.id as row_id', DB::raw("'day' as row_type"), 'a.user_id', 'a.date', DB::raw("'{$dir}' as direction"),
                    "a.{$c['time']} as punched_at", DB::raw("{$daySource} as source"), 'a.attendance_type as method',
                    DB::raw('NULL as biometric_device_id'), "a.{$c['lat']} as lat", "a.{$c['lng']} as lng",
                    "a.{$c['addr']} as address", 'a.location_verification', 'a.branch_id as attendance_location_id',
                    "a.{$c['dist']} as distance_meters", DB::raw('NULL as accuracy_meters'), 'a.device_id',
                    DB::raw('NULL as network_type'), 'a.wifi_ssid', 'a.ip_address', DB::raw('NULL as battery_percent'),
                    DB::raw('1 as session_seq'), 'a.is_regularized', 'a.marked_by as actor_id', 'a.remarks as reason',
                ]);
        };

        $union = $punches->unionAll($dayRow('in'))->unionAll($dayRow('out'));

        $q = DB::query()->fromSub($union, 'r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('user_job_details as j', 'j.user_id', '=', 'r.user_id')
            ->leftJoin('departments as dp', 'dp.id', '=', 'j.department')
            ->leftJoin('designations as dg', 'dg.id', '=', 'j.designation')
            ->leftJoin('company_branches as cb', 'cb.id', '=', 'j.branch_id')
            ->leftJoin('attendance_locations as al', 'al.id', '=', 'r.attendance_location_id')
            // The employee's assigned attendance location (vs. `al` = the one this punch was checked against).
            ->leftJoin('attendance_locations as ual', 'ual.id', '=', 'j.office_branch')
            ->leftJoin('biometric_devices as bd', 'bd.id', '=', 'r.biometric_device_id')
            ->leftJoin('users as act', 'act.id', '=', 'r.actor_id')
            ->where('u.tenant_id', $tenantId)
            ->select([
                'r.*', 'u.name as employee_name', 'u.employee_id as employee_code', 'u.email as employee_email',
                'dp.name as department_name', 'dg.name as designation_name', 'cb.name as branch_name',
                'j.office_branch', 'ual.name as assigned_location_name',
                'al.name as location_name', 'al.radius', 'bd.name as terminal_name', 'act.name as actor_name',
            ]);

        $auth = Auth::user();
        if ($auth->role === 'manager') {
            $q->whereIn('r.user_id', User::where('tenant_id', $tenantId)->managedBy($auth->id)->pluck('id'));
        }
        if ($f['search'] !== '') {
            $s = '%' . addcslashes($f['search'], '%_\\') . '%';
            $q->where(fn ($w) => $w->where('u.name', 'LIKE', $s)->orWhere('u.employee_id', 'LIKE', $s));
        }
        foreach (['user_id' => 'r.user_id', 'department_id' => 'j.department', 'designation_id' => 'j.designation',
            'branch_id' => 'j.branch_id', 'location_id' => 'j.office_branch', 'direction' => 'r.direction', 'source' => 'r.source'] as $key => $col) {
            if ($f[$key] !== null) {
                $q->where($col, $f[$key]);
            }
        }

        return $q;
    }

    private function options(int $tenantId): array
    {
        $employees = User::where('tenant_id', $tenantId)->where('role', '!=', 'superadmin');
        if (Auth::user()->role === 'manager') {
            $employees->managedBy(Auth::id());
        }

        return [
            'employees' => $employees->orderBy('name')->get(['id', 'name', 'employee_id']),
            'departments' => Department::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'branches' => CompanyBranch::where('tenant_id', $tenantId)->active()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
