<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding module rebuild — step 3/7.
 *
 * Live `exit_interviews` never had a `status` column at all — the old
 * controller wrote `'status' => 'completed'` into create()/update() calls,
 * which Eloquent silently dropped because it wasn't in the model's
 * $fillable and the column didn't exist, so every real row lost its status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exit_interviews', function (Blueprint $table) {
            if (! Schema::hasColumn('exit_interviews', 'status')) {
                $table->enum('status', ['scheduled', 'completed', 'declined'])
                    ->default('scheduled')
                    ->after('interview_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exit_interviews', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
