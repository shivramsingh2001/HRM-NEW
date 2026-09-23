<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve/reject whichever approval level (manager or HR) is currently
 * pending for the request — OffboardingService::decide() resolves which
 * level applies via ApprovalService, so one request/route/controller method
 * covers both stages instead of near-duplicate manager-review/hr-review pairs.
 */
class DecideOffboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:1000',
        ];
    }
}
