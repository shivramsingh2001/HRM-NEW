<?php

namespace App\Http\Requests\Api;

/** POST /api/floating-weekoffs (and GET …/check with only `date`) — plan a floating week-off. */
class PlanFloatingWeekOffRequest extends MobileApiRequest
{
    public function rules(): array
    {
        return ['date' => 'required|date_format:Y-m-d', 'reason' => 'nullable|string|max:500'];
    }
}
