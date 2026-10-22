<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A department name saved with a trailing line break (old data / imports) used to be echoed into the
 * chart script as a '...' string, which broke the whole script — every admin dashboard chart went blank.
 */
class DashboardChartScriptSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_department_name_with_line_break_does_not_break_chart_script(): void
    {
        $dept = DB::table('departments')
            ->join('user_job_details', 'departments.id', '=', 'user_job_details.department')
            ->join('users', 'users.id', '=', 'user_job_details.user_id')
            ->where('users.status', 1)
            ->whereExists(fn ($q) => $q->from('users as a')->whereColumn('a.tenant_id', 'departments.tenant_id')->where('a.role', 'admin')->where('a.status', 1))
            ->select('departments.id', 'departments.tenant_id')
            ->first();
        if (! $dept) {
            $this->markTestSkipped('No department with active staff under an admin tenant in this DB.');
        }

        DB::table('departments')->where('id', $dept->id)->update(['name' => "ZZ Chart Test\r\n"]);
        $admin = User::withoutGlobalScopes()->where('tenant_id', $dept->tenant_id)->where('role', 'admin')->where('status', 1)->orderBy('id')->first();

        $html = $this->actingAs($admin)->withSession(['tenant_id' => $admin->tenant_id])->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('deptNames.push("ZZ Chart Test");', $html);
        $this->assertStringNotContainsString("deptNames.push('ZZ Chart Test\r\n", $html);
    }

    public function test_department_name_is_trimmed_on_save(): void
    {
        $this->assertSame('Accounts', (new Department(['name' => " Accounts\r\n"]))->name);
    }
}
