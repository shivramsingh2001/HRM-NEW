<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteVisit;
use App\Services\Site\SiteException;
use App\Services\Site\SiteVisitReportService;
use App\Services\Site\SiteVisitService;
use App\Support\Spreadsheet\SpreadsheetWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Reports → Site Visit Reports: day-wise visits, monthly per-employee
 * summary and one employee's month. Who is covered follows the viewer's
 * site_visits:view scope (see SiteVisitReportService).
 */
class SiteVisitReportController extends Controller
{
    public function __construct(private SiteVisitReportService $reports) {}

    public function dayWise(Request $request)
    {
        $filters = $this->dayFilters($request);

        return view('client.report.site-visit.day-wise', [
            'visits' => $this->reports->dayWise(Auth::user(), $filters),
            'filters' => $filters,
            'employees' => $this->reports->employeeOptions(Auth::user()),
            'sites' => Site::where('tenant_id', Auth::user()->tenant_id)->orderBy('site_name')->get(['id', 'site_code', 'site_name']),
        ]);
    }

    public function dayWiseExport(Request $request, SpreadsheetWriter $writer)
    {
        $filters = $this->dayFilters($request);
        $rows = [['Employee', 'Employee ID', 'Site Code', 'Site Name', 'District', 'Time', 'Contact Person', 'Contact Mobile', 'Remarks', 'File', 'Latitude', 'Longitude']];
        foreach ($this->reports->dayWiseQuery(Auth::user(), $filters)->cursor() as $v) {
            /** @var SiteVisit $v */
            $rows[] = [
                $v->user->name ?? '', $v->user->employee_id ?? '', $v->site->site_code ?? '', $v->site->site_name ?? '', $v->site->district ?? '',
                $v->visited_at->format('h:i A'), $v->contact_person_name, $v->contact_mobile, $v->remarks,
                $v->file_path ? file_url($v->file_path, SiteVisitService::FILE_MODULE) : '', $v->latitude, $v->longitude,
            ];
        }

        return $this->download($writer, ['Visits '.$filters['date'] => $rows], "site-visits-{$filters['date']}.xlsx");
    }

    public function monthly(Request $request)
    {
        $month = SiteVisitReportService::month($request->input('month'));
        $userId = $request->integer('user_id') ?: null;

        return view('client.report.site-visit.monthly', [
            'rows' => $this->reports->monthlySummary(Auth::user(), $month, $userId),
            'month' => $month,
            'userId' => $userId,
            'employees' => $this->reports->employeeOptions(Auth::user()),
        ]);
    }

    public function monthlyExport(Request $request, SpreadsheetWriter $writer)
    {
        $month = SiteVisitReportService::month($request->input('month'));
        $rows = [['Employee', 'Employee ID', 'Total Visits', 'Different Sites', 'Days with a Visit', 'Last Visit']];
        foreach ($this->reports->monthlySummary(Auth::user(), $month, $request->integer('user_id') ?: null) as $r) {
            $rows[] = [$r['name'], $r['employee_id'], $r['visits'], $r['sites'], $r['days'],
                $r['last_visit'] ? date('d-m-Y h:i A', strtotime($r['last_visit'])) : ''];
        }

        return $this->download($writer, ['Visits '.$month => $rows], "site-visits-monthly-{$month}.xlsx");
    }

    public function employee(Request $request, $userId)
    {
        $month = SiteVisitReportService::month($request->input('month'));
        try {
            $data = $this->reports->employeeMonth(Auth::user(), (int) $userId, $month);
        } catch (SiteException $e) {
            abort(403, $e->getMessage());
        }

        return view('client.report.site-visit.employee', $data);
    }

    /** @return array<string,mixed> */
    private function dayFilters(Request $request): array
    {
        $filters = SiteVisitService::cleanFilters($request->only(['date', 'user_id', 'site_id']));
        $filters['date'] = min($filters['date'] ?? now()->toDateString(), now()->toDateString());

        return $filters;
    }

    /** @param array<string,array<int,array<int,mixed>>> $sheets */
    private function download(SpreadsheetWriter $writer, array $sheets, string $name)
    {
        return response()->download($writer->write($sheets), $name)->deleteFileAfterSend();
    }
}
