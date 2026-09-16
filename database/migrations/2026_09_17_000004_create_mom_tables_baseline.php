<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `meetings` and `meeting_participants` tables (Meeting Management /
     * Minutes-of-Meeting module) were originally created via raw SQL / DB
     * import and had NO migration history at all before this rebuild. This
     * migration formalizes their schema-as-it-already-exists (reverse
     * engineered from a live `SHOW CREATE TABLE` against hrm_22_04) so a
     * fresh install/CI environment can rebuild them identically — it is a
     * documentation/portability baseline, not a structural change, and is a
     * no-op on any environment where these tables already exist (guarded by
     * hasTable() on every table). Same pattern as
     * 2026_09_12_195000_create_legacy_payroll_tables_baseline.php.
     *
     * All later mom/meeting migrations (attendance columns, mom_status,
     * meeting_histories, etc.) assume these tables already exist in this
     * pre-rebuild shape.
     */
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            Schema::create('meetings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->nullable();
                $table->string('meeting_id', 20);
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('agenda')->nullable();
                $table->enum('meeting_type', ['physical', 'virtual', 'hybrid'])->default('physical');
                $table->enum('meeting_mode', ['in_person', 'video_conference', 'phone_call'])->default('in_person');
                $table->string('virtual_meeting_link', 500)->nullable();
                $table->string('location')->nullable();
                $table->date('meeting_date');
                $table->time('start_time');
                $table->time('end_time');
                // duration_minutes is a generated/virtual column in the live
                // DB (TIMESTAMPDIFF(MINUTE, meeting_date+start_time,
                // meeting_date+end_time)) — not reproducible via a portable
                // Blueprint call across DB drivers, so it's intentionally
                // left out of this baseline. Any fresh-install environment
                // that needs it should add it via a driver-specific raw
                // statement; every current reader treats it as optional.
                $table->unsignedBigInteger('created_by');
                $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled', 'postponed'])->default('scheduled');
                $table->text('cancellation_reason')->nullable();
                $table->text('mom_content')->nullable();
                $table->integer('reminder_minutes_before')->default(15);
                $table->boolean('reminder_sent')->default(false);
                $table->unsignedBigInteger('parent_meeting_id')->nullable();
                $table->enum('recurrence_pattern', ['none', 'daily', 'weekly', 'monthly', 'custom'])->default('none');
                $table->text('recurrence_rule')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('parent_meeting_id')->references('id')->on('meetings')->onDelete('set null');
                $table->index(['meeting_date', 'status']);
                $table->index('status');
                $table->index('tenant_id');
            });
        }

        if (! Schema::hasTable('meeting_participants')) {
            Schema::create('meeting_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('meeting_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('role', ['organizer', 'presenter', 'attendee', 'optional'])->default('attendee');
                $table->boolean('is_mom_writer')->default(false);
                $table->enum('attendance_status', ['pending', 'confirmed', 'declined', 'tentative', 'late', 'absent', 'present'])->default('pending');
                $table->boolean('reminder_sent')->default(false);
                $table->timestamps();

                $table->unique(['meeting_id', 'user_id']);
                $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index('is_mom_writer');
                $table->index('attendance_status');
                $table->index('user_id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: this is a baseline for tables that predate
        // migration history. Dropping them here would be destructive to
        // pre-existing production data on any environment that runs the
        // down migration, which is never the intent of a baseline.
    }
};
