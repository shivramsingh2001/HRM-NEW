<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Offboarding module rebuild — step 4/7.
 *
 * Drops the hand-written `update_offboarding_status` BEFORE UPDATE trigger
 * (confirmed live via `SHOW TRIGGERS` — not present in any prior migration).
 * It silently rewrote `status` from the review/stage columns on every save,
 * which OffboardingController had to work around by hand for `cancel()`
 * (no branch existed for status='cancelled' until the trigger was patched).
 * All transitions it used to perform now live in `OffboardingService`, the
 * single writer of `status`/`current_stage`.
 *
 * `generate_offboarding_request_code` (the BEFORE INSERT trigger that fills
 * `request_code`) is left untouched — it is unrelated and still useful.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_offboarding_status');
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER update_offboarding_status
BEFORE UPDATE ON offboarding_requests
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' THEN
        SET NEW.status = 'cancelled';
    ELSEIF NEW.manager_review_status = 'rejected' OR NEW.hr_review_status = 'rejected' THEN
        SET NEW.status = 'rejected';
    ELSEIF NEW.offboarding_completed_at IS NOT NULL OR
           (NEW.knowledge_transfer_status = 'completed' AND
            NEW.asset_return_status = 'completed' AND
            NEW.final_settlement_status = 'paid') THEN
        SET NEW.status = 'completed';
    ELSEIF NEW.manager_review_status = 'approved' AND
           NEW.hr_review_status = 'approved' AND
           NEW.last_working_date IS NOT NULL THEN
        SET NEW.status = 'approved';
    ELSE
        SET NEW.status = 'pending_approval';
    END IF;
END
SQL);
    }
};
