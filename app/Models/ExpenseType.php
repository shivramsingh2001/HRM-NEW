<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class ExpenseType extends Model
{
    use TenantTrait;
    protected $guarded = [];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_type');
    }
}
