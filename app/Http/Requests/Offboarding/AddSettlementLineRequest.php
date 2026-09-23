<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class AddSettlementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'line_type' => 'required|in:severance,custom_addition,custom_deduction',
            'is_addition' => 'required|boolean',
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0|max:99999999.99',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
