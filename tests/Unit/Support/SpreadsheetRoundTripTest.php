<?php

namespace Tests\Unit\Support;

use App\Support\Spreadsheet\SpreadsheetReader;
use App\Support\Spreadsheet\SpreadsheetWriter;
use PHPUnit\Framework\TestCase;

/** SpreadsheetWriter output must read back unchanged through SpreadsheetReader (no DB). */
class SpreadsheetRoundTripTest extends TestCase
{
    public function test_written_xlsx_reads_back_first_sheet_only(): void
    {
        $rows = [
            ['Site Name*', 'State*', 'Address*'],
            ['A & B <Office>', 'Uttar Pradesh', "Plot \"7\", Sector 62"],
            ['', 'Delhi', 'Row with an empty first cell'],
        ];
        $path = (new SpreadsheetWriter())->write(['Sites' => $rows, 'States' => [['State'], ['Delhi']]]);

        try {
            $this->assertSame($rows, (new SpreadsheetReader())->readXlsx($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_columns_past_z_keep_their_position(): void
    {
        $row = array_map(fn ($i) => "c{$i}", range(0, 29));
        $path = (new SpreadsheetWriter())->write(['Wide' => [$row]]);

        try {
            $this->assertSame([$row], (new SpreadsheetReader())->readXlsx($path));
        } finally {
            @unlink($path);
        }
    }
}
