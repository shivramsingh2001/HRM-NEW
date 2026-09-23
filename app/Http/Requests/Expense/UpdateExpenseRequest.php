<?php

namespace App\Http\Requests\Expense;

/** Same rules as creating an expense; ownership/status are checked in the controller. */
class UpdateExpenseRequest extends StoreExpenseRequest
{
}
