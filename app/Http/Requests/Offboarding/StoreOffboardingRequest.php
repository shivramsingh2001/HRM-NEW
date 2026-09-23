<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class StoreOffboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // reason-specific eligibility is enforced by OffboardingService::submit() via config('offboarding.reason_rules')
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|exists:users,id',
            'reason' => 'required|in:resignation,retirement,termination,contract_end,mutual_agreement,other',
            'resignation_date' => 'nullable|date|before_or_equal:today',
            'last_working_date' => 'required|date|after_or_equal:today',
            'reason_detail' => 'nullable|string|max:2000',
            'feedback' => 'nullable|string|max:2000',
            'eligible_for_rehire' => 'nullable|boolean',
        ];
    }
}
