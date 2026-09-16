<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail `Meeting::histories()` always intended to have —
     * the model relation and the `MeetingHistory::create(...)` call sites
     * already existed in the controllers (commented out) pointing at a
     * class that was never built. This table finishes that: every create/
     * update/cancel/reschedule/destroy/attendance/MOM-finalize action gets
     * a row here so meeting changes finally leave a trail.
     */
    public function up(): void
    {
        if (! Schema::hasTable('meeting_histories')) {
            Schema::create('meeting_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('meeting_id');
                $table->unsignedBigInteger('action_by')->nullable();
                $table->string('action_type', 40);
                $table->text('description')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
                $table->foreign('action_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['meeting_id', 'created_at']);
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_histories');
    }
};
