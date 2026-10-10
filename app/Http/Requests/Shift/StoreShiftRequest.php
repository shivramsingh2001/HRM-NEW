<?php

namespace App\Http\Requests\Shift;

/** Shifts → Add Shift (POST shift.store). */
class StoreShiftRequest extends ShiftDefinitionRequest
{
    public function rules(): array
    {
        return $this->shiftRules($this->uniqueShiftName());
    }
}
