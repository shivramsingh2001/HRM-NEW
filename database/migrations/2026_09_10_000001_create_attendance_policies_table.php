<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 / W1 — per-tenant, versioned attendance policy.
 *
 * Replaces the scattered rule sources:
 *   - config('attendance.ratio.*') / config('attendance.fallback_hours.*')
 *   - AttendanceSummaryService::OVERTIME_THRESHOLD
 *   - tenants.late_halfday_enabled / tenants.monthly_late_allowance
 *
 * A row applies to every day >= effective_from. The seeded row with a NULL
 * tenant_id is the global default and is exactly today's behaviour, so an
 * un-configured tenant is unaffected. PolicyResolver still dual-reads the
 * tenants.* columns until a follow-up migration drops them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_policies')) {
            Schema::create('attendance_policies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->date('effective_from')->default('2000-01-01');

                // Day classification (ratio of worked / scheduled-expected).
                $table->decimal('present_ratio', 4, 3)->default(0.900);
                $table->decimal('half_day_ratio', 4, 3)->default(0.500);
                // Absolute-hours ladder used when no shift expectation exists.
                $table->decimal('fallback_present_hours', 4, 2)->default(8.00);
                $table->decimal('fallback_half_hours', 4, 2)->default(4.00);
                $table->decimal('full_day_min_hours', 4, 2)->nullable();

                // Overtime.
                $table->decimal('overtime_after_hours', 4, 2)->default(9.00);
                $table->decimal('overtime_multiplier', 3, 2)->default(1.00);

                // Late handling.
                $table->smallInteger('grace_minutes')->default(0);
                $table->smallInteger('rounding_minutes')->default(0);
                $table->boolean('late_halfday_enabled')->default(false);
                $table->smallInteger('monthly_late_allowance')->default(30);

                // Working-time-directive advisory thresholds (not enforced yet).
                $table->decimal('min_rest_hours', 4, 2)->nullable();
                $table->decimal('max_daily_hours', 4, 2)->nullable();
                $table->boolean('sandwich_leave')->default(false);

                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'effective_from'], 'attn_policy_tenant_effective_uq');
            });
        }

        // Seed the global default = current config values (behaviour-preserving).
        $exists = DB::table('attendance_policies')->whereNull('tenant_id')->exists();
        if (! $exists) {
            DB::table('attendance_policies')->insert([
                'tenant_id' => null,
                'effective_from' => '2000-01-01',
                'present_ratio' => (float) config('attendance.ratio.present', 0.90),
                'half_day_ratio' => (float) config('attendance.ratio.half', 0.50),
                'fallback_present_hours' => (float) config('attendance.fallback_hours.present', 8),
                'fallback_half_hours' => (float) config('attendance.fallback_hours.half', 4),
                'full_day_min_hours' => null,
                'overtime_after_hours' => (float) config('attendance.overtime_after_hours', 9),
                'overtime_multiplier' => 1.00,
                'grace_minutes' => 0,
                'rounding_minutes' => 0,
                'late_halfday_enabled' => false,
                'monthly_late_allowance' => 30,
                'sandwich_leave' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // One row per tenant that has already customised the late allowance, so
        // LatePolicyService keeps reading the same numbers after it switches to
        // the resolver. effective_from is deliberately epoch — these tenants have
        // been running this rule for the whole of their history.
        $tenants = DB::table('tenants')
            ->where(function ($q) {
                $q->where('late_halfday_enabled', 1)
                    ->orWhereNotNull('monthly_late_allowance');
            })
            ->get(['id', 'late_halfday_enabled', 'monthly_late_allowance']);

        foreach ($tenants as $t) {
            $already = DB::table('attendance_policies')
                ->where('tenant_id', $t->id)
                ->where('effective_from', '2000-01-01')
                ->exists();
            if ($already) {
                continue;
            }

            DB::table('attendance_policies')->insert([
                'tenant_id' => $t->id,
                'effective_from' => '2000-01-01',
                'present_ratio' => (float) config('attendance.ratio.present', 0.90),
                'half_day_ratio' => (float) config('attendance.ratio.half', 0.50),
                'fallback_present_hours' => (float) config('attendance.fallback_hours.present', 8),
                'fallback_half_hours' => (float) config('attendance.fallback_hours.half', 4),
                'overtime_after_hours' => (float) config('attendance.overtime_after_hours', 9),
                'overtime_multiplier' => 1.00,
                'grace_minutes' => 0,
                'rounding_minutes' => 0,
                'late_halfday_enabled' => (bool) $t->late_halfday_enabled,
                'monthly_late_allowance' => $t->monthly_late_allowance ?? 30,
                'sandwich_leave' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
