<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Shift\WeeklyOffWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Assign Shift → weekly week-off: same employee + day + start date with a
 * different end date used to hit the unique_weekoff key (1062 duplicate entry).
 * Shared dev DB, rolled back via DatabaseTransactions.
 */
class WeeklyOffWriterTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::withoutGlobalScopes()->whereNotNull('tenant_id')->where('role', 'employee')->first();
        if (! $user) {
            $this->markTestSkipped('no employee in the dev DB');
        }
        $this->user = $user;
        DB::table('user_weekoffs')->where('user_id', $user->id)->where('off_type', 'day_based')->where('day_name', 'Sunday')->delete();
    }

    private function save(?string $from, ?string $to): string
    {
        return app(WeeklyOffWriter::class)->save($this->user->id, (int) $this->user->tenant_id, 'Sunday', $from, $to, (int) $this->user->id);
    }

    private function rows()
    {
        return DB::table('user_weekoffs')->where('user_id', $this->user->id)->where('off_type', 'day_based')->where('day_name', 'Sunday')->get();
    }

    public function test_same_start_with_a_new_end_date_updates_instead_of_failing(): void
    {
        $this->assertSame('created', $this->save('2026-11-01', '2026-11-30'));
        $this->assertSame('updated', $this->save('2026-11-01', '2026-12-31'));   // was: 1062 Duplicate entry

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame('2026-12-31', substr((string) $rows[0]->end_date, 0, 10));
        $this->assertSame((int) $this->user->tenant_id, (int) $rows[0]->tenant_id);
    }

    public function test_exact_repeat_is_unchanged_and_switched_off_row_comes_back(): void
    {
        $this->save('2026-11-01', '2026-12-31');
        $this->assertSame('unchanged', $this->save('2026-11-01', '2026-12-31'));

        DB::table('user_weekoffs')->where('user_id', $this->user->id)->where('day_name', 'Sunday')->update(['status' => 0]);
        $this->assertSame('updated', $this->save('2026-11-01', '2026-12-31'));
        $this->assertSame(1, (int) $this->rows()[0]->status);
    }

    public function test_open_ended_and_different_start_dates_are_separate_rows(): void
    {
        $this->assertSame('created', $this->save(null, null));
        $this->assertSame('unchanged', $this->save(null, null));
        $this->assertSame('created', $this->save('2027-01-01', '2027-02-28'));
        $this->assertCount(2, $this->rows());
    }
}
