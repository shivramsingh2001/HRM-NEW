<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tasks') || Schema::hasColumn('tasks', 'deadline_reminder_sent_at')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('deadline_reminder_sent_at')->nullable()->after('deadline_date');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'deadline_reminder_sent_at')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('deadline_reminder_sent_at');
            });
        }
    }
};
