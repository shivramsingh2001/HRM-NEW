<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Company Policies → Comp-off card. */
class UpdateCompOffSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'comp_off_enabled' => ['nullable', 'boolean'],
            'comp_off_mode' => ['required', 'in:request,auto'],
            'comp_off_full_day_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'comp_off_half_day_hours' => ['required', 'numeric', 'min:0', 'max:24', 'lte:comp_off_full_day_hours'],
            'comp_off_expiry_days' => ['required', 'integer', 'min:0', 'max:365'],
            'comp_off_monthly_cap' => ['required', 'integer', 'min:0', 'max:31'],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        $data['comp_off_enabled'] = $this->boolean('comp_off_enabled');

        return $data;
    }
}
