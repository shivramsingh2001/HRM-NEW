<?php

namespace Tests\Feature;

use App\Services\FieldTracking\ArchivingPruner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * GPS prune must archive every row it deletes — including the cutoff's partial
 * month, which the old whole-month logic deleted without a backup (2026-10-11).
 *
 * Uses its own scratch table + archive dir on the shared dev DB; both are
 * removed in tearDown.
 */
class ArchivingPrunerTest extends TestCase
{
    private string $table = 'zz_test_gps_prune';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists($this->table);
        Schema::create($this->table, function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->dateTime('track_time');
            $t->decimal('lat', 10, 7);
        });

        $rows = [];
        foreach (['2026-06-20 10:00:00', '2026-06-25 10:00:00', '2026-07-05 10:00:00', '2026-07-12 10:00:00', '2026-07-20 10:00:00'] as $i => $time) {
            $rows[] = ['tenant_id' => 7, 'track_time' => $time, 'lat' => 28.5 + $i / 10];
        }
        $rows[] = ['tenant_id' => 8, 'track_time' => '2026-06-01 10:00:00', 'lat' => 1]; // other tenant: untouched
        DB::table($this->table)->insert($rows);

        $this->dir = storage_path('framework/testing/gps-archive-'.uniqid());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists($this->table);
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function lines(string $file): array
    {
        $path = "{$this->dir}/{$file}";

        return File::exists($path) ? array_values(array_filter(explode("\n", File::get($path)))) : [];
    }

    public function test_partial_month_is_archived_before_delete_and_appended_day_by_day(): void
    {
        $pruner = new ArchivingPruner;

        // Night 1: cutoff 10 Jul — June whole + 5 Jul (partial July) are due.
        $this->assertSame(3, $pruner->prune($this->table, 7, Carbon::parse('2026-07-10'), 2, $this->dir));
        $this->assertCount(2, $this->lines('2026-06.ndjson'));
        $this->assertCount(1, $this->lines('2026-07.ndjson'), 'the partial month must be archived too');

        // Night 2: cutoff 13 Jul — only 12 Jul is newly due; it is APPENDED to the July file.
        $this->assertSame(1, $pruner->prune($this->table, 7, Carbon::parse('2026-07-13'), 2, $this->dir));
        $july = $this->lines('2026-07.ndjson');
        $this->assertCount(2, $july);
        $this->assertSame('2026-07-12 10:00:00', json_decode($july[1], true)['track_time']);

        // Re-run with the same cutoff: nothing deleted, nothing archived twice.
        $this->assertSame(0, $pruner->prune($this->table, 7, Carbon::parse('2026-07-13'), 2, $this->dir));
        $this->assertCount(2, $this->lines('2026-06.ndjson'));
        $this->assertCount(2, $this->lines('2026-07.ndjson'));

        // Kept: the newer row of tenant 7 and the other tenant's row.
        $this->assertSame(['2026-07-20 10:00:00'], DB::table($this->table)->where('tenant_id', 7)->pluck('track_time')->all());
        $this->assertSame(1, DB::table($this->table)->where('tenant_id', 8)->count());
    }

    public function test_every_deleted_row_is_in_the_archive(): void
    {
        $before = DB::table($this->table)->where('tenant_id', 7)->where('track_time', '<', '2026-07-13')->pluck('id')->sort()->values()->all();

        (new ArchivingPruner)->prune($this->table, 7, Carbon::parse('2026-07-13'), 2, $this->dir);

        $archived = collect(array_merge($this->lines('2026-06.ndjson'), $this->lines('2026-07.ndjson')))
            ->map(fn ($l) => json_decode($l, true)['id'])->sort()->values()->all();
        $this->assertSame($before, $archived);
    }

    public function test_without_archive_dir_it_only_deletes(): void
    {
        $this->assertSame(4, (new ArchivingPruner)->prune($this->table, 7, Carbon::parse('2026-07-13'), 100, null));
        $this->assertDirectoryDoesNotExist($this->dir);
    }
}
