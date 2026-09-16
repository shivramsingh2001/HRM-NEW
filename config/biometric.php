<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingest
    |--------------------------------------------------------------------------
    */
    'ingest_batch_max' => (int) env('BIOMETRIC_INGEST_BATCH_MAX', 500),

    // A same-direction punch within this many seconds of an existing one is
    // treated as a double-tap and skipped.
    'dedupe_window_seconds' => (int) env('BIOMETRIC_DEDUPE_WINDOW_SECONDS', 60),

    // Order in which the punch direction (in/out) is decided:
    //   payload      - the bridge already tagged it
    //   device_mode  - biometric_devices.direction_mode (in|out|by_verify_mode)
    //   verify_mode  - byte 1 of the packed raw_verify_mode
    //   auto         - no clock_in yet today => in, else => out
    'direction_priority' => ['payload', 'device_mode', 'verify_mode', 'auto'],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    | biometric:prune deletes processed/skipped biometric_punches older than
    | this. The attendances they produced are never touched.
    */
    'punch_retention_days' => (int) env('BIOMETRIC_PUNCH_RETENTION_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Roster auto-provisioning
    |--------------------------------------------------------------------------
    | The bridge creates/updates/removes employees on the terminal from HRM,
    | keyed by users.id, so no enroll number is ever mapped by hand.
    */
    'roster' => [
        // Max length of the name string written to the device.
        'name_max_len' => (int) env('BIOMETRIC_ROSTER_NAME_MAX_LEN', 40),

        // Append " (EMP_ID)" to the pushed name.
        'name_include_employee_id' => (bool) env('BIOMETRIC_ROSTER_NAME_WITH_EMP_ID', true),

        // biometric:sync-roster cadence (also set the schedule in routes/console.php).
        'sync_interval_minutes' => (int) env('BIOMETRIC_ROSTER_SYNC_MINUTES', 15),

        // On a punch whose enroll_no is an unmapped numeric users.id, auto-create
        // the enrollment row instead of skipping it.
        'auto_resolve_punch_enrolls' => (bool) env('BIOMETRIC_ROSTER_AUTO_RESOLVE', true),

        // Integer base the bridge parses users.card_number with before pushing it
        // to the device. 10 = decimal (as printed on most cards); 16 for hex.
        'card_number_base' => (int) env('BIOMETRIC_CARD_NUMBER_BASE', 10),
    ],

];
