<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class UserJobDetail extends Model
{
    use TenantTrait;
     protected $guarded = [];
     
      public function Designation()
     {
          return $this->belongsTo(Designation::class, 'designation');
     }
     public function Department()
     {
          return $this->belongsTo(Department::class, 'department');
     }
     /**
      * Attendance geofence location (renamed from Branch() 2026_09_20 —
      * this is the attendance check-in location, not organizational
      * structure; column name office_branch kept unchanged).
      */
     public function attendanceLocation()
     {
          return $this->belongsTo(AttendanceLocation::class, 'office_branch');
     }

     /**
      * Organizational Branch membership — separate from attendanceLocation()
      * above. Optional; only used by multi-location companies.
      */
     public function branch()
     {
          return $this->belongsTo(CompanyBranch::class, 'branch_id');
     }
     public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function departmentRel()
    {
        return $this->belongsTo(Department::class, 'department');
    }

    public function designationRel()
    {
        return $this->belongsTo(Designation::class, 'designation');
    }

    public function reportingHead()
    {
        return $this->belongsTo(User::class, 'reporting_head');
    }
     public function offboardingRequest()
    {
        // Local key is UserJobDetail.user_id; the owner key on
        // offboarding_requests is employee_id (that table has no user_id
        // column at all) — this previously joined on a column that doesn't
        // exist and never resolved.
        return $this->belongsTo(OffboardingRequest::class, 'user_id', 'employee_id')
            ->where('status', 'completed');
    }

    // Check if employee has left
    public function getHasLeftAttribute()
    {
        return !is_null($this->leaving_date) && $this->leaving_date <= now();
    }
}
