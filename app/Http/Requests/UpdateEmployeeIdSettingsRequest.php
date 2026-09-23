<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeIdSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id_prefix' => ['required', 'string', 'regex:/^[A-Za-z]{2}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_id_prefix.regex' => 'The prefix must be exactly 2 letters (e.g. SH).',
        ];
    }
}
