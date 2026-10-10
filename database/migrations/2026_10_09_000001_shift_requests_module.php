<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shift Requests module (2026-10-09) — one migration for the whole feature:
 *
 *  - shift_change_logs        append-only trail of every change to a user's shift on a day
 *  - shift_assignments        + source values (day_override / swap / change_request / rotation),
 *                             + type 'rotating', is_override, reason, shift_request_id, rotation columns
 *  - shift_requests / _items / _events   employee swap + change requests (and admin direct swaps)
 *  - shift_request_settings   Company Policies → Shift Requests (one row per company)
 *  - shift_rotation_patterns / _steps    rotating shift patterns
 *  - shifts                   + shift allowance (per day / per hour)
 *  - monthly_payrolls         + shift_allowance_amount / shift_allowance_days
 *
 * No FK constraints, matching shifts / user_shifts / shift_assignments.
 * Every step is guarded so the migration can be re-run safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_change_logs')) {
            Schema::create('shift_change_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->date('date');
                $table->boolean('is_additional')->default(false);
                $table->unsignedBigInteger('from_shift_id')->nullable();
                $table->unsignedBigInteger('to_shift_id')->nullable();
                $table->enum('change_type', ['assign', 'change', 'remove', 'swap', 'request_change', 'rotation', 'revert']);
                $table->string('source', 40);
                $table->unsignedBigInteger('shift_request_id')->nullable();
                $table->unsignedBigInteger('shift_assignment_id')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_role', 30)->nullable();
                $table->string('reason', 500)->nullable();
                $table->enum('channel', ['web', 'mobile', 'system'])->default('web');
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'user_id', 'date'], 'scl_tenant_user_date_idx');
                $table->index(['tenant_id', 'created_at'], 'scl_tenant_created_idx');
                $table->index('shift_request_id', 'scl_request_idx');
            });
        }

        DB::statement("ALTER TABLE shift_assignments MODIFY type ENUM('permanent','flexible','rotating') NOT NULL");
        DB::statement("ALTER TABLE shift_assignments MODIFY source ENUM('manual','migration_backfill','day_override','swap','change_request','rotation') NOT NULL DEFAULT 'manual'");

        Schema::table('shift_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_assignments', 'is_override')) {
                // One-day exception (roster edit, swap, approved change request):
                // beats every Permanent/Flexible row for its date and is re-applied last on rebuild.
                $table->boolean('is_override')->default(false)->after('is_additional');
            }
            if (! Schema::hasColumn('shift_assignments', 'reason')) {
                $table->string('reason', 500)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('shift_assignments', 'shift_request_id')) {
                $table->unsignedBigInteger('shift_request_id')->nullable()->after('source');
            }
            if (! Schema::hasColumn('shift_assignments', 'rotation_pattern_id')) {
                $table->unsignedBigInteger('rotation_pattern_id')->nullable()->after('shift_request_id');
            }
            if (! Schema::hasColumn('shift_assignments', 'rotation_anchor_date')) {
                $table->date('rotation_anchor_date')->nullable()->after('rotation_pattern_id');
            }
        });

        if (! Schema::hasTable('shift_requests')) {
            Schema::create('shift_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('request_no', 30);
                $table->enum('type', ['swap', 'change']);
                $table->enum('mode', ['request', 'direct'])->default('request');
                $table->unsignedBigInteger('requester_id');
                $table->unsignedBigInteger('counterpart_id')->nullable();
                $table->enum('status', ['pending_peer', 'pending_approval', 'approved', 'rejected', 'peer_declined', 'cancelled', 'expired', 'reverted']);
                $table->string('reason', 500)->nullable();
                $table->string('peer_remarks', 500)->nullable();
                $table->string('approver_remarks', 500)->nullable();
                $table->timestamp('peer_responded_at')->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->unsignedBigInteger('reverted_by')->nullable();
                $table->timestamp('reverted_at')->nullable();
                $table->unsignedBigInteger('approval_request_id')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->enum('channel', ['web', 'mobile'])->default('web');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'request_no'], 'sr_tenant_no_uq');
                $table->index(['tenant_id', 'status'], 'sr_tenant_status_idx');
                $table->index(['tenant_id', 'requester_id'], 'sr_tenant_requester_idx');
                $table->index(['tenant_id', 'counterpart_id'], 'sr_tenant_counterpart_idx');
            });
        }

        if (! Schema::hasTable('shift_request_items')) {
            Schema::create('shift_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('shift_request_id');
                $table->unsignedBigInteger('user_id');
                $table->date('date');
                $table->unsignedBigInteger('from_shift_id')->nullable();
                $table->unsignedBigInteger('to_shift_id');
                // Assignment that owned the day before (restored on revert) and the override written on approval.
                $table->unsignedBigInteger('previous_assignment_id')->nullable();
                $table->unsignedBigInteger('applied_assignment_id')->nullable();
                $table->timestamps();

                $table->index('shift_request_id', 'sri_request_idx');
                $table->index(['tenant_id', 'user_id', 'date'], 'sri_tenant_user_date_idx');
            });
        }

        if (! Schema::hasTable('shift_request_events')) {
            Schema::create('shift_request_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('shift_request_id');
                $table->string('event', 40);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_role', 30)->nullable();
                $table->string('remarks', 500)->nullable();
                $table->json('meta')->nullable();
                $table->enum('channel', ['web', 'mobile', 'system'])->default('web');
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('shift_request_id', 'sre_request_idx');
            });
        }

        if (! Schema::hasTable('shift_request_settings')) {
            Schema::create('shift_request_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique();
                $table->boolean('swap_enabled')->default(true);
                $table->boolean('change_enabled')->default(true);
                $table->boolean('requires_approval')->default(true);
                $table->unsignedSmallInteger('min_notice_hours')->default(24);
                $table->unsignedSmallInteger('min_rest_hours')->default(8);
                $table->unsignedSmallInteger('max_requests_per_month')->default(4);
                $table->unsignedSmallInteger('peer_response_hours')->default(24);
                $table->boolean('same_department_only')->default(true);
                $table->boolean('same_branch_only')->default(false);
                $table->boolean('notify_on_roster_change')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shift_rotation_patterns')) {
            Schema::create('shift_rotation_patterns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 100);
                $table->string('description', 255)->nullable();
                $table->unsignedSmallInteger('cycle_days');
                $table->boolean('status')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'name'], 'srp_tenant_name_uq');
            });
        }

        if (! Schema::hasTable('shift_rotation_steps')) {
            Schema::create('shift_rotation_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pattern_id');
                $table->unsignedSmallInteger('day_index');
                // null = day off in the cycle (no shift that day)
                $table->unsignedBigInteger('shift_id')->nullable();

                $table->unique(['pattern_id', 'day_index'], 'srs_pattern_day_uq');
            });
        }

        Schema::table('shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('shifts', 'allowance_type')) {
                $table->enum('allowance_type', ['none', 'per_day', 'per_hour'])->default('none')->after('break_time');
            }
            if (! Schema::hasColumn('shifts', 'allowance_amount')) {
                $table->decimal('allowance_amount', 10, 2)->default(0)->after('allowance_type');
            }
            if (! Schema::hasColumn('shifts', 'allowance_min_hours')) {
                $table->decimal('allowance_min_hours', 5, 2)->nullable()->after('allowance_amount');
            }
        });

        Schema::table('monthly_payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_payrolls', 'shift_allowance_amount')) {
                $table->decimal('shift_allowance_amount', 12, 2)->default(0)->after('overtime_amount');
            }
            if (! Schema::hasColumn('monthly_payrolls', 'shift_allowance_days')) {
                $table->decimal('shift_allowance_days', 6, 2)->default(0)->after('shift_allowance_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_payrolls', function (Blueprint $table) {
            foreach (['shift_allowance_days', 'shift_allowance_amount'] as $c) {
                if (Schema::hasColumn('monthly_payrolls', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('shifts', function (Blueprint $table) {
            foreach (['allowance_min_hours', 'allowance_amount', 'allowance_type'] as $c) {
                if (Schema::hasColumn('shifts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        foreach (['shift_rotation_steps', 'shift_rotation_patterns', 'shift_request_settings', 'shift_request_events', 'shift_request_items', 'shift_requests', 'shift_change_logs'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('shift_assignments', function (Blueprint $table) {
            foreach (['rotation_anchor_date', 'rotation_pattern_id', 'shift_request_id', 'reason', 'is_override'] as $c) {
                if (Schema::hasColumn('shift_assignments', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        DB::statement("UPDATE shift_assignments SET source = 'manual' WHERE source NOT IN ('manual','migration_backfill')");
        DB::statement("ALTER TABLE shift_assignments MODIFY source ENUM('manual','migration_backfill') NOT NULL DEFAULT 'manual'");
        DB::statement("ALTER TABLE shift_assignments MODIFY type ENUM('permanent','flexible') NOT NULL");
    }
};
