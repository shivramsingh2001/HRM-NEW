<?php

namespace App\Services\Performance;

/**
 * The ONE canonical weighted-average + grade calculation for the whole
 * Performance module. Replaces four previously-independent, mutually
 * inconsistent implementations (PerformanceCalculationService,
 * EmployeeKpiScore::getOverallScoreAttribute(),
 * PerformanceController::calculateOverallScore(),
 * ManagerPerformanceReviewController::recalculateOverallScore()).
 *
 * Every consumer of a blended/graded score in this module must go through
 * this class — never re-implement a weighted average or a grade scale
 * elsewhere.
 */
class PerformanceScoreCalculator
{
    /**
     * Blend component scores using the given weights, excluding a component
     * from the average ONLY when its value is PHP null (a day/period it
     * genuinely doesn't apply to) — never for a legitimately-earned 0.0.
     * Remaining weights are renormalized to sum to 100%.
     *
     * @param  array<string,float|null>  $components  e.g. ['attendance' => 82.0, 'task_completion' => null]
     * @param  array<string,float>  $weights          e.g. ['attendance' => 30.0, 'task_completion' => 20.0]
     * @return array{score: ?float, included: array<int,string>, weights_used: array<string,float>}
     */
    public function blend(array $components, array $weights): array
    {
        $included = [];
        $totalWeight = 0.0;

        foreach ($components as $key => $value) {
            if ($value === null) {
                continue;
            }
            $included[] = $key;
            $totalWeight += (float) ($weights[$key] ?? 0.0);
        }

        if (empty($included) || $totalWeight <= 0.0) {
            return ['score' => null, 'included' => [], 'weights_used' => []];
        }

        $weightedSum = 0.0;
        $weightsUsed = [];
        foreach ($included as $key) {
            $normalizedWeight = ((float) $weights[$key]) / $totalWeight * 100.0;
            $weightsUsed[$key] = round($normalizedWeight, 2);
            $weightedSum += ((float) $components[$key]) * ($normalizedWeight / 100.0);
        }

        return [
            'score' => round($weightedSum, 2),
            'included' => $included,
            'weights_used' => $weightsUsed,
        ];
    }

    /**
     * The single canonical 10-band grade scale for the whole module.
     */
    public function grade(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 90 => 'A+',
            $score >= 85 => 'A',
            $score >= 80 => 'A-',
            $score >= 75 => 'B+',
            $score >= 70 => 'B',
            $score >= 65 => 'B-',
            $score >= 60 => 'C+',
            $score >= 55 => 'C',
            $score >= 50 => 'C-',
            $score >= 45 => 'D',
            default => 'F',
        };
    }
}
