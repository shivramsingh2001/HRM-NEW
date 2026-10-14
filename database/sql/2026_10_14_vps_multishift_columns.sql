-- =====================================================================
-- VPS (hrm_application, MySQL 8.4) — columns missing as of 2026-10-14.
-- Same result as these two Laravel migrations:
--   2026_10_13_000001_extra_shift_pay_mode
--   2026_10_14_000001_shift_segments_is_extra
-- No new tables — only new columns on existing ones.
--
-- Use EITHER this file OR `php artisan migrate --path=...` — not both.
-- Step 5 records both migrations as run, so a later `php artisan migrate`
-- will not try to add the columns again.
-- Take a backup first:
--   mysqldump hrm_application overtime_settings monthly_payrolls attendance_shift_segments attendances > before_2026_10_14.sql
-- =====================================================================

-- 1. Company Policies → "Additional (2nd) shift pay": overtime hours or extra working day
ALTER TABLE `overtime_settings`
    ADD COLUMN `extra_shift_pay_mode` ENUM('overtime','extra_day') NOT NULL DEFAULT 'overtime' AFTER `fixed_rate_per_hour`;

-- 2. Payslip: extra working days (2nd shifts paid as days)
ALTER TABLE `monthly_payrolls`
    ADD COLUMN `extra_work_days`       DECIMAL(6,2)  NOT NULL DEFAULT 0 AFTER `shift_allowance_days`,
    ADD COLUMN `extra_work_day_rate`   DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `extra_work_days`,
    ADD COLUMN `extra_work_day_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `extra_work_day_rate`;

-- 3. Multi-shift day: is this shift paid as EXTRA (beyond the day's normal shift)?
ALTER TABLE `attendance_shift_segments`
    ADD COLUMN `is_extra` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_additional`;

-- 4. Back-fill existing days with the same rule as the code:
--    the day's NORMAL shift = the main shift (is_additional = 0) if worked,
--    else the earliest shift worked; every other shift that day is extra.
UPDATE `attendance_shift_segments` s
JOIN (
    SELECT x.attendance_id,
           (SELECT s2.id FROM `attendance_shift_segments` s2
             WHERE s2.attendance_id = x.attendance_id
             ORDER BY s2.is_additional, s2.scheduled_start, s2.id
             LIMIT 1) AS normal_id
      FROM (SELECT DISTINCT attendance_id FROM `attendance_shift_segments`) x
) n ON n.attendance_id = s.attendance_id
SET s.is_extra = IF(s.id = n.normal_id, 0, 1);

--    attendances.extra_shift_minutes = minutes of the extra shifts only
UPDATE `attendances` a
JOIN (
    SELECT attendance_id, SUM(IF(is_extra = 1, worked_minutes, 0)) AS extra_minutes
      FROM `attendance_shift_segments`
     GROUP BY attendance_id
) t ON t.attendance_id = a.id
SET a.extra_shift_minutes = t.extra_minutes;

-- 5. Record both migrations as run (so `php artisan migrate` skips them)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.name, (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`)
  FROM (SELECT '2026_10_13_000001_extra_shift_pay_mode' AS name
        UNION ALL SELECT '2026_10_14_000001_shift_segments_is_extra') m
 WHERE m.name NOT IN (SELECT migration FROM `migrations`);

-- 6. Check
SELECT 'overtime_settings.extra_shift_pay_mode' AS col, COUNT(*) AS found FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'overtime_settings' AND column_name = 'extra_shift_pay_mode'
UNION ALL SELECT 'monthly_payrolls.extra_work_*', COUNT(*) FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'monthly_payrolls' AND column_name IN ('extra_work_days','extra_work_day_rate','extra_work_day_amount')
UNION ALL SELECT 'attendance_shift_segments.is_extra', COUNT(*) FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'attendance_shift_segments' AND column_name = 'is_extra';
-- expected: 1, 3, 1
