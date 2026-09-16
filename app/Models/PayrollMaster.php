<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use App\Traits\LogsPayrollActivity;

class PayrollMaster extends Model
{
    use TenantTrait, LogsPayrollActivity;
    protected $guarded = [];
}
