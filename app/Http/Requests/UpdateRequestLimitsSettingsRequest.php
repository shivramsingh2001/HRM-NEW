<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestLimitsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> — empty / 0 = no limit */
    public function rules(): array
    {
        return [
            'wfh_max_days_per_month' => ['nullable', 'integer', 'min:0', 'max:31'],
            'wfh_min_notice_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'regularization_max_per_month' => ['nullable', 'integer', 'min:0', 'max:31'],
            'regularization_max_days_back' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
