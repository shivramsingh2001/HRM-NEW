<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'deadline_reminder_sent_at')) {
                $table->timestamp('deadline_reminder_sent_at')->nullable()->after('progress_manual_override');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'deadline_reminder_sent_at')) {
                $table->dropColumn('deadline_reminder_sent_at');
            }
        });
    }
};
