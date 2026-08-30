<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class AttendanceLog extends Model
{
     use TenantTrait;
     protected $guarded = [];
}
