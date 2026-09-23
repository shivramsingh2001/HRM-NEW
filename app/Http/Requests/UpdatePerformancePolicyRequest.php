<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformancePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin', 'hr'], true);
    }

    public function rules(): array
    {
        return [
            'effective_from' => ['nullable', 'date'],

            'weight_attendance' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_task_completion' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_task_ontime' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_project_participation' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_regularization' => ['required', 'numeric', 'min:0', 'max:100'],
            'weight_manager_rating' => ['required', 'numeric', 'min:0', 'max:100'],

            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'late_penalty_per_incident' => ['required', 'numeric', 'min:0', 'max:100'],
            'late_penalty_cap' => ['required', 'numeric', 'min:0', 'max:100'],
            'early_departure_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'early_departure_penalty_per_incident' => ['required', 'numeric', 'min:0', 'max:100'],
            'early_departure_penalty_cap' => ['required', 'numeric', 'min:0', 'max:100'],

            'regularization_penalty_approved' => ['required', 'numeric', 'min:0', 'max:100'],
            'regularization_penalty_rejected' => ['required', 'numeric', 'min:0', 'max:100'],
            'regularization_penalty_pending' => ['required', 'numeric', 'min:0', 'max:100'],
            'regularization_penalty_cap' => ['required', 'numeric', 'min:0', 'max:100'],

            'task_overdue_penalty_per_task' => ['required', 'numeric', 'min:0', 'max:100'],
            'task_overdue_penalty_cap' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_tasks_for_task_score' => ['required', 'integer', 'min:0', 'max:50'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $sum = (float) $this->input('weight_attendance', 0)
                + (float) $this->input('weight_task_completion', 0)
                + (float) $this->input('weight_task_ontime', 0)
                + (float) $this->input('weight_project_participation', 0)
                + (float) $this->input('weight_regularization', 0)
                + (float) $this->input('weight_manager_rating', 0);

            if (abs($sum - 100.0) > 0.5) {
                $validator->errors()->add('weight_attendance', "Weights must sum to 100 (currently {$sum}).");
            }
        });
    }

    /**
     * Only the policy columns (drop effective_from — the controller handles it).
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        unset($data['effective_from']);

        return $data;
    }
}
