<?php

namespace App\Http\Requests\Api;

/** POST /api/manager/…/{id}/decision — approve or reject. */
class ApprovalDecisionRequest extends MobileApiRequest
{
    public function rules(): array
    {
        return ['action' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:500'];
    }

    public function messages(): array
    {
        return ['action.in' => 'action must be approved or rejected.'];
    }
}
