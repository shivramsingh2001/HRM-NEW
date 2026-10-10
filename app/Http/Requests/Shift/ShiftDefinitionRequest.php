<?php

namespace App\Http\Requests\Shift;

use App\Support\ShiftWindow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared rules of the shift definition form (Shifts → Add / Edit). The Shifts
 * page posts by AJAX and reads `{status: false, errors: {...}}` on a 422, so a
 * failure keeps that exact shape (the code-quality plan's FormRequest pilot —
 * see docs/architecture.md → Coding conventions).
 */
abstract class ShiftDefinitionRequest extends FormRequest
{
    /** Route middleware (role:admin,hr) already decides who may get here. */
    public function authorize(): bool
    {
        return true;
    }

    protected function shiftRules(Unique $uniqueName): array
    {
        return [
            // Shift names are unique per company, not across all companies.
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'start_time' => 'required|date_format:H:i',
            // Overnight shifts end on/before their start time — checked in withValidator().
            'end_time' => 'required|date_format:H:i',
            'is_overnight' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
            'grace_minutes' => 'nullable|integer|min:0|max:120',
            'color_code' => 'nullable|string|max:7',
            'break_time' => 'nullable|integer|min:0|max:180',
            'allowance_type' => 'nullable|in:none,per_day,per_hour',
            'allowance_amount' => 'nullable|numeric|min:0|max:99999999',
            'allowance_min_hours' => 'nullable|numeric|min:0|max:24',
        ];
    }

    protected function uniqueShiftName(): Unique
    {
        return Rule::unique('shifts', 'name')->where('tenant_id', $this->user()->tenant_id);
    }

    /** Times must agree with the "Overnight shift" checkbox (ShiftWindow::timesError). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->hasAny(['start_time', 'end_time'])) {
                return;
            }
            $error = ShiftWindow::timesError($this->start_time, $this->end_time, $this->boolean('is_overnight'));
            if ($error) {
                $v->errors()->add('end_time', $error);
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'errors' => $validator->errors(),
        ], 422));
    }
}
