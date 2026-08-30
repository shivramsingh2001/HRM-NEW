<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class AttendanceRegularization extends Model
{
     use TenantTrait;
     protected $guarded = [];
     
     Public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the approver (manager/admin who approved/rejected)
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Alias for approver relationship (if you need both names)
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

   
}
