<?php

namespace App\Http\Requests\Api;

/** POST /api/comp-off — claim a worked week-off / holiday. */
class ClaimCompOffRequest extends MobileApiRequest
{
    public function rules(): array
    {
        return ['worked_date' => 'required|date_format:Y-m-d', 'reason' => 'nullable|string|max:500'];
    }
}
