<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class OverrideSettlementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => 'nullable|numeric|min:0|max:99999999.99',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
