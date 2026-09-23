<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClearanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => 'required|in:completed,waived,not_applicable,pending',
            'remarks' => 'nullable|string|max:500',
        ];
    }
}
