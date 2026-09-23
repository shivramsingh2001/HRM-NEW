<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class DecideNoticeOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'approve' => 'required|boolean',
            'decision_notes' => 'nullable|string|max:1000',
        ];
    }
}
