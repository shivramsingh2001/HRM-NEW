<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin', 'hr'], true);
    }

    public function rules(): array
    {
        return [
            'field_tracking_ping_seconds' => [
                'required', 'integer',
                'min:' . (int) config('location.min_ping_seconds', 30),
                'max:' . (int) config('location.max_ping_seconds', 900),
            ],
            'field_tracking_retention_days' => [
                'required', 'integer',
                'min:' . (int) config('location.retention_floor_days', 30),
                'max:730',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'field_tracking_ping_seconds.min' => 'The ping interval cannot be shorter than '
                . config('location.min_ping_seconds', 30) . ' seconds.',
            'field_tracking_retention_days.min' => 'Retention cannot be shorter than '
                . config('location.retention_floor_days', 30) . ' days.',
        ];
    }
}
