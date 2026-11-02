<?php

namespace Tests\Unit\Services;

use App\Http\Requests\Site\SiteVisitRules;
use App\Services\Site\SiteVisitService;
use PHPUnit\Framework\TestCase;

/** Site visit input rules and list filters (no DB). */
class SiteVisitRulesTest extends TestCase
{
    public function test_date_is_never_an_input_and_site_is_fixed_on_edit(): void
    {
        $create = SiteVisitRules::rules(false);
        $update = SiteVisitRules::rules(true);

        $this->assertArrayNotHasKey('visit_date', $create);
        $this->assertArrayNotHasKey('visit_date', $update);
        $this->assertArrayHasKey('site_id', $create);
        $this->assertArrayNotHasKey('site_id', $update);
        $this->assertStringStartsWith('required', $create['remarks']);
        $this->assertStringStartsWith('nullable', $create['file']);
        $this->assertStringContainsString('digits:10', $create['contact_mobile']);
    }

    public function test_report_month_defaults_and_never_goes_past_this_month(): void
    {
        $this->assertSame('2026-10', \App\Services\Site\SiteVisitReportService::month('2026-10'));
        $this->assertSame(now()->format('Y-m'), \App\Services\Site\SiteVisitReportService::month('bad'));
        $this->assertSame(now()->format('Y-m'), \App\Services\Site\SiteVisitReportService::month('2999-01'));
    }

    public function test_bad_filters_are_dropped(): void
    {
        $this->assertSame(
            ['date' => '2026-11-02', 'month' => '2026-11', 'site_id' => 7],
            SiteVisitService::cleanFilters(['date' => '2026-11-02', 'month' => '2026-11', 'site_id' => '7'])
        );
        $this->assertSame([], SiteVisitService::cleanFilters(['date' => '02-11-2026', 'month' => '2026-13', 'site_id' => 'x']));
    }
}
