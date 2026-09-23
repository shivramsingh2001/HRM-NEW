<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoticePeriodSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notice_period' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }
}
