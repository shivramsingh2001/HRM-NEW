<?php

return [
    // Days before a subscription's end date (or a trial's trial_ends_at) that
    // the in-app "plan ending soon" banner starts showing to every logged-in
    // user. Mirrors the superadmin panel's lifecycle.trial_reminder_days window,
    // but as a single threshold since this banner is shown continuously rather
    // than fired once per day-count like the email reminders.
    'warning_days' => env('SUBSCRIPTION_WARNING_DAYS', 7),
];
