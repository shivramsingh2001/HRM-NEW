<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

/** Shift → Week-off Swaps → New swap. */
class StoreWeekOffSwapRequest extends FormRequest
{
    /** Route middleware (role:admin,hr) already decides who may get here. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worked_date' => 'required|date_format:Y-m-d',
            'replacement_date' => 'required|date_format:Y-m-d|different:worked_date',
            'reason' => 'nullable|string|max:500',
            'employees' => 'required|array|min:1|max:1000',
            'employees.*.user_id' => 'required|integer|distinct',
            'employees.*.replacement_date' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'employees.required' => 'Pick at least one employee.',
            'replacement_date.different' => 'The day off must differ from the worked day.',
        ];
    }
}
