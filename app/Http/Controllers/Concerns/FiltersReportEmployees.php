<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * The four employee filters every attendance report offers — Branch,
 * Department, Designation, Attendance Location — applied the same way
 * everywhere. Dropdowns: resources/views/client/report/partials/employee-filters.blade.php.
 *
 * Query params (both spellings accepted, since older reports use either):
 *   branch_id, department|department_id, designation|designation_id, location_id
 * location_id = user_job_details.office_branch (attendance_locations.id; 0 = "any location").
 */
trait FiltersReportEmployees
{
    /** Filter values from the request, normalised to ints (null when not set). */
    protected function reportEmployeeFilters(Request $request): array
    {
        $int = fn ($v) => ($v === null || $v === '') ? null : (int) $v;

        return [
            'branch_id' => $int($request->query('branch_id')),
            'department' => $int($request->query('department', $request->query('department_id'))),
            'designation' => $int($request->query('designation', $request->query('designation_id'))),
            'location_id' => $int($request->query('location_id')),
        ];
    }

    /**
     * Apply the filters to a query that has user_job_details joined.
     * $jobAlias is that join's alias ('uj' in most reports).
     */
    protected function applyReportEmployeeFilters($query, Request $request, string $jobAlias = 'uj')
    {
        $f = $this->reportEmployeeFilters($request);

        return $query
            ->when($f['branch_id'] !== null, fn ($q) => $q->where("{$jobAlias}.branch_id", $f['branch_id']))
            ->when($f['department'] !== null, fn ($q) => $q->where("{$jobAlias}.department", $f['department']))
            ->when($f['designation'] !== null, fn ($q) => $q->where("{$jobAlias}.designation", $f['designation']))
            ->when($f['location_id'] !== null, fn ($q) => $q->where("{$jobAlias}.office_branch", $f['location_id']));
    }

    /** Same filters for an Eloquent User query (whereHas on jobDetails). */
    protected function applyReportEmployeeFiltersToUsers($userQuery, Request $request)
    {
        $f = $this->reportEmployeeFilters($request);
        if (array_filter($f, fn ($v) => $v !== null) === []) {
            return $userQuery;
        }

        return $userQuery->whereHas('jobDetails', fn ($q) => $q
            ->when($f['branch_id'] !== null, fn ($j) => $j->where('branch_id', $f['branch_id']))
            ->when($f['department'] !== null, fn ($j) => $j->where('department', $f['department']))
            ->when($f['designation'] !== null, fn ($j) => $j->where('designation', $f['designation']))
            ->when($f['location_id'] !== null, fn ($j) => $j->where('office_branch', $f['location_id'])));
    }
}
