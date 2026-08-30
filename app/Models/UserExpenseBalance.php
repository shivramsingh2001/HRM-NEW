<?php
// app/Models/UserExpenseBalance.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class UserExpenseBalance extends Model
{
    use TenantTrait, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'advance_balance' => 'decimal:2',
        'settlement_balance' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function updateBalances($advanceAmount = 0, $settlementAmount = 0)
    {
        $this->advance_balance += $advanceAmount;
        $this->settlement_balance += $settlementAmount;
        $this->current_balance = $this->advance_balance - $this->settlement_balance;
        $this->save();

        return $this;
    }

    public function addAdvance($amount)
    {
        return $this->updateBalances($amount, 0);
    }

    public function addSettlement($amount)
    {
        return $this->updateBalances(0, $amount);
    }

    public function canSettle($amount)
    {
        return $this->current_balance >= $amount;
    }
}
