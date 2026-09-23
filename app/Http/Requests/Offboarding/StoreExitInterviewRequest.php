<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class StoreExitInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'interview_date' => 'required|date',
            'work_environment_rating' => 'nullable|integer|min:1|max:5',
            'management_rating' => 'nullable|integer|min:1|max:5',
            'career_growth_rating' => 'nullable|integer|min:1|max:5',
            'compensation_rating' => 'nullable|integer|min:1|max:5',
            'work_life_balance_rating' => 'nullable|integer|min:1|max:5',
            'primary_reason' => 'nullable|string|max:2000',
            'what_would_improve' => 'nullable|string|max:2000',
            'would_recommend' => 'nullable|boolean',
            'feedback_comments' => 'nullable|string|max:2000',
            'suggestions' => 'nullable|string|max:2000',
        ];
    }
}
