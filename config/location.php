<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Field GPS tracking — cadence
    |--------------------------------------------------------------------------
    |
    | ping_seconds returned to the mobile app (in /today and /track responses)
    | is the tenant's field_tracking_ping_seconds clamped to [min, max]; default
    | is used when the tenant column is 0 / null.
    |
    */
    'min_ping_seconds' => (int) env('LOCATION_MIN_PING_SECONDS', 30),
    'max_ping_seconds' => (int) env('LOCATION_MAX_PING_SECONDS', 900),
    'default_ping_seconds' => (int) env('LOCATION_DEFAULT_PING_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Ingest mode
    |--------------------------------------------------------------------------
    |
    | 'single' — app posts one point per ping to /track (current behaviour).
    | 'batch'  — app buffers points and flushes to /track-batch. Flip only when
    |            the mobile build supports buffering.
    |
    */
    'mode' => env('LOCATION_TRACKING_MODE', 'single'),
    'batch_max' => (int) env('LOCATION_BATCH_MAX', 60),

    /*
    |--------------------------------------------------------------------------
    | Safety limiter
    |--------------------------------------------------------------------------
    |
    | Per-user cap on ingest requests. Set well above the real cadence — this is
    | a runaway-client guard, not a shaper.
    |
    */
    'throttle_per_minute' => (int) env('LOCATION_THROTTLE_PER_MINUTE', 40),

    /*
    |--------------------------------------------------------------------------
    | Server-side enforcement
    |--------------------------------------------------------------------------
    |
    | When true, /track and /track-batch write nothing (and tell the app to
    | stop) for employees whose location_tracking_enabled is off. Keep false
    | until the pilot app build reads location_tracking.enabled from /today.
    |
    */
    'enforce_enabled' => (bool) env('LOCATION_ENFORCE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Async ingest (needs a running queue worker on the "attendance" queue)
    |--------------------------------------------------------------------------
    */
    'async_ingest' => (bool) env('LOCATION_ASYNC_INGEST', false),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | field-tracking:prune deletes attendance_tracks older than the tenant's
    | field_tracking_retention_days, never newer than retention_floor_days.
    | archive_before_prune writes each whole month to NDJSON before deleting it.
    |
    */
    'retention_floor_days' => (int) env('LOCATION_RETENTION_FLOOR_DAYS', 30),
    'archive_before_prune' => (bool) env('LOCATION_ARCHIVE_BEFORE_PRUNE', true),

];
