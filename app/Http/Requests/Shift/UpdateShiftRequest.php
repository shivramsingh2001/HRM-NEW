<?php

namespace App\Http\Requests\Shift;

use App\Models\Shift;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Shifts → Edit Shift (POST shift.update/{id}). */
class UpdateShiftRequest extends ShiftDefinitionRequest
{
    /** A missing shift answers 404 before the form is validated (as before). */
    protected function prepareForValidation(): void
    {
        if (! Shift::find($this->route('id'))) {
            throw new HttpResponseException(response()->json([
                'status' => false,
                'message' => 'Shift not found',
            ], 404));
        }
    }

    public function rules(): array
    {
        return $this->shiftRules($this->uniqueShiftName()->ignore($this->route('id'))) + [
            'status' => 'nullable|boolean',
        ];
    }
}
