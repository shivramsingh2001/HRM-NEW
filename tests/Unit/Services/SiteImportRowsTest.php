<?php

namespace Tests\Unit\Services;

use App\Services\Site\SiteException;
use App\Services\Site\SiteImportService;
use App\Services\Site\SiteService;
use App\Support\Spreadsheet\SpreadsheetReader;
use App\Support\Spreadsheet\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Site import row reading and checks that need no database. */
class SiteImportRowsTest extends TestCase
{
    private function service(): SiteImportService
    {
        $sites = (new \ReflectionClass(SiteService::class))->newInstanceWithoutConstructor();

        return new SiteImportService($sites, new SpreadsheetReader(), new SpreadsheetWriter());
    }

    private function call(string $method, ...$args)
    {
        $m = new ReflectionMethod(SiteImportService::class, $method);
        $m->setAccessible(true);

        return $m->invoke($this->service(), ...$args);
    }

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'site').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'sites.csv', 'text/csv', null, true);
    }

    public function test_reads_rows_by_header_name_in_any_order_and_skips_blank_rows(): void
    {
        $rows = $this->call('readRows', $this->csv(
            "\xEF\xBB\xBFAddress*,State*,Site Name*,Onboarding Date\n"
            ."\"A-12,  Sector 62\",Uttar Pradesh,Noida Office,15-10-2026\n"
            .",,,\n"
            ."B-1,Delhi,Delhi Office,\n"
        ));

        $this->assertSame([2, 4], array_keys($rows));
        $this->assertSame('Noida Office', $rows[2]['site_name']);
        $this->assertSame('A-12, Sector 62', $rows[2]['address']);
        $this->assertSame('', $rows[4]['district']);
    }

    public function test_missing_required_header_is_reported(): void
    {
        $this->expectException(SiteException::class);
        $this->expectExceptionMessage('State');
        $this->call('readRows', $this->csv("Site Name,Address\nX,Y\n"));
    }

    public function test_dates_from_text_and_excel_serials(): void
    {
        $this->assertSame('2026-10-15', $this->call('parseDate', '15-10-2026'));
        $this->assertSame('2026-10-15', $this->call('parseDate', '15/10/2026'));
        $this->assertSame('2026-10-15', $this->call('parseDate', '2026-10-15'));
        $this->assertSame('2026-10-15', $this->call('parseDate', '46310'));
        $this->assertNull($this->call('parseDate', '31-02-2026'));
        $this->assertNull($this->call('parseDate', 'next week'));
    }

    public function test_row_checks(): void
    {
        $states = ['uttarpradesh' => 9, 'up' => 9, '9' => 9];
        $row = ['site_name' => 'Noida Office', 'state' => 'Uttar  Pradesh', 'district' => '', 'address' => 'A-12',
            'location' => '', 'latitude' => '28.627', 'longitude' => '77.3727', 'onboarding_date' => ''];

        [$data, $errors] = $this->call('checkRow', $row, $states);
        $this->assertSame([], $errors);
        $this->assertSame(9, $data['state_code']);
        $this->assertNull($data['district']);
        $this->assertSame(28.627, $data['latitude']);

        [, $errors] = $this->call('checkRow', ['site_name' => '', 'state' => 'Goa', 'address' => '', 'latitude' => '28', 'longitude' => ''] + $row, $states);
        $this->assertCount(4, $errors); // name, address, unknown state, latitude without longitude
    }
}
