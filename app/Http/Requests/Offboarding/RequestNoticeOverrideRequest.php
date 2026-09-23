<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class RequestNoticeOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => 'required|in:waiver,early_release,extension',
            'requested_last_working_date' => 'required|date',
            'reason' => 'required|string|max:1000',
        ];
    }
}
