<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkingTimeThresholdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'min_rest_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'max_daily_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'sandwich_leave' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        $data['sandwich_leave'] = $this->boolean('sandwich_leave');

        return $data;
    }
}
