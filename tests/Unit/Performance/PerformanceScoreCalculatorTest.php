<?php

namespace Tests\Unit\Performance;

use App\Services\Performance\PerformanceScoreCalculator;
use PHPUnit\Framework\TestCase;

class PerformanceScoreCalculatorTest extends TestCase
{
    private PerformanceScoreCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new PerformanceScoreCalculator();
    }

    public function test_blend_all_components_present(): void
    {
        $result = $this->calc->blend(
            ['attendance' => 80.0, 'task_completion' => 60.0],
            ['attendance' => 70.0, 'task_completion' => 30.0]
        );

        // 80*0.7 + 60*0.3 = 56 + 18 = 74
        $this->assertSame(74.0, $result['score']);
        $this->assertSame(['attendance', 'task_completion'], $result['included']);
    }

    public function test_blend_renormalizes_when_one_component_missing(): void
    {
        $result = $this->calc->blend(
            ['attendance' => 80.0, 'manager_rating' => null],
            ['attendance' => 85.0, 'manager_rating' => 15.0]
        );

        // manager_rating excluded entirely; attendance's weight renormalized to 100%.
        $this->assertSame(80.0, $result['score']);
        $this->assertSame(['attendance'], $result['included']);
        $this->assertSame(100.0, $result['weights_used']['attendance']);
    }

    public function test_blend_returns_null_when_everything_missing(): void
    {
        $result = $this->calc->blend(
            ['attendance' => null, 'manager_rating' => null],
            ['attendance' => 85.0, 'manager_rating' => 15.0]
        );

        $this->assertNull($result['score']);
        $this->assertSame([], $result['included']);
    }

    public function test_a_legitimate_zero_is_never_treated_as_missing(): void
    {
        $result = $this->calc->blend(
            ['attendance' => 80.0, 'task_completion' => 0.0],
            ['attendance' => 50.0, 'task_completion' => 50.0]
        );

        // 0.0 must still be included and weighted, not dropped like the old
        // truthiness-gated accessors did (`if ($score > 0)`).
        $this->assertSame(['attendance', 'task_completion'], $result['included']);
        $this->assertSame(40.0, $result['score']); // 80*0.5 + 0*0.5
    }

    /** @dataProvider gradeBoundaries */
    public function test_grade_boundaries(float $score, string $expected): void
    {
        $this->assertSame($expected, $this->calc->grade($score));
    }

    public static function gradeBoundaries(): array
    {
        return [
            [95, 'A+'], [90, 'A+'], [89.9, 'A'],
            [85, 'A'], [84.9, 'A-'],
            [80, 'A-'], [79.9, 'B+'],
            [75, 'B+'], [74.9, 'B'],
            [70, 'B'], [69.9, 'B-'],
            [65, 'B-'], [64.9, 'C+'],
            [60, 'C+'], [59.9, 'C'],
            [55, 'C'], [54.9, 'C-'],
            [50, 'C-'], [49.9, 'D'],
            [45, 'D'], [44.9, 'F'],
            [0, 'F'],
        ];
    }

    public function test_grade_is_null_for_null_score(): void
    {
        $this->assertNull($this->calc->grade(null));
    }
}
