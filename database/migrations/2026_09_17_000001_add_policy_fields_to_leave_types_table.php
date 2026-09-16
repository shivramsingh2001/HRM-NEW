<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-type leave policy fields (Leave Management Phase 1). Nullable/defaulted
 * so every existing leave type keeps today's behavior (unlimited, no notice
 * requirement) until a tenant explicitly opts a type into a policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_types', 'max_carry_forward')) {
                $table->decimal('max_carry_forward', 6, 2)->nullable()->after('is_unpaid');
            }
            if (! Schema::hasColumn('leave_types', 'carry_forward_expiry_months')) {
                $table->unsignedSmallInteger('carry_forward_expiry_months')->nullable()->after('max_carry_forward');
            }
            if (! Schema::hasColumn('leave_types', 'is_encashable')) {
                $table->boolean('is_encashable')->default(false)->after('carry_forward_expiry_months');
            }
            if (! Schema::hasColumn('leave_types', 'min_notice_days')) {
                $table->unsignedSmallInteger('min_notice_days')->nullable()->after('is_encashable');
            }
            if (! Schema::hasColumn('leave_types', 'max_consecutive_days')) {
                $table->unsignedSmallInteger('max_consecutive_days')->nullable()->after('min_notice_days');
            }
            if (! Schema::hasColumn('leave_types', 'requires_document_after_days')) {
                $table->unsignedSmallInteger('requires_document_after_days')->nullable()->after('max_consecutive_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            foreach ([
                'max_carry_forward', 'carry_forward_expiry_months', 'is_encashable',
                'min_notice_days', 'max_consecutive_days', 'requires_document_after_days',
            ] as $col) {
                if (Schema::hasColumn('leave_types', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
