<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Designation extends Model
{
    use TenantTrait;
    protected $guarded = [];
}
