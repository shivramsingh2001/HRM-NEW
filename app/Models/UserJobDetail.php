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
     public function Branch()
     {
          return $this->belongsTo(Branch::class, 'office_branch');
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
        return $this->belongsTo(OffboardingRequest::class, 'user_id', 'user_id')
            ->where('status', 'completed');
    }

    // Check if employee has left
    public function getHasLeftAttribute()
    {
        return !is_null($this->leaving_date) && $this->leaving_date <= now();
    }
}
