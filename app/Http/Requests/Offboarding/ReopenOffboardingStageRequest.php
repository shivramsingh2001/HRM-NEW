<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class ReopenOffboardingStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage' => 'required|in:knowledge_transfer,clearance,exit_interview,settlement',
            'reason' => 'required|string|max:1000',
        ];
    }
}
