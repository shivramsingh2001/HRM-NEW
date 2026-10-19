<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Company Policies → Floating week-offs card. */
class UpdateFloatingWeekoffsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'floating_weekoffs_enabled' => ['nullable', 'boolean'],
            'floating_weekoffs_per_month' => ['required', 'integer', 'min:0', 'max:15'],
            'floating_weekoffs_max_per_week' => ['required', 'integer', 'min:0', 'max:7'],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        $data['floating_weekoffs_enabled'] = $this->boolean('floating_weekoffs_enabled');

        return $data;
    }
}
