<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'applies_to' => 'array',
    ];

    public function steps()
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')->orderBy('level');
    }

    /**
     * Does this workflow apply to the given subject user (by department /
     * designation / attendance location / organizational branch)? A null
     * applies_to matches everyone.
     */
    public function matchesUser(?User $user): bool
    {
        $scope = $this->applies_to;
        if (empty($scope) || ! $user) {
            return true;
        }

        $jd = $user->jobDetails;
        $checks = [
            'departments' => $jd->department ?? null,
            'designations' => $jd->designation ?? null,
            // Renamed from 'branches' (2026_09_20) — this checks
            // office_branch, the attendance-geofencing location, not the
            // organizational Branch below.
            'attendance_locations' => $jd->office_branch ?? null,
            // Organizational Branch (company_branches.id) — separate from
            // attendance_locations above.
            'branches' => $jd->branch_id ?? null,
        ];

        foreach ($checks as $key => $value) {
            $allowed = $scope[$key] ?? [];
            if (! empty($allowed) && ! in_array($value, $allowed)) {
                return false;
            }
        }

        return true;
    }
}
