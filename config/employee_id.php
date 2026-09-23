<?php

return [
    // Used for any tenant that hasn't set tenants.employee_id_prefix yet
    // (matches the value every tenant was hardcoded to before this became
    // configurable).
    'default_prefix' => env('EMPLOYEE_ID_DEFAULT_PREFIX', 'SH'),

    // Digits the numeric part of the ID is zero-padded to (SH + 6 digits =
    // SH000123). Not tenant-configurable — changing this retroactively would
    // desync it from every already-issued employee_id.
    'padding_length' => 6,
];
