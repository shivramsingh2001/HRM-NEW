<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit log HMAC signing key
    |--------------------------------------------------------------------------
    |
    | Signs rows written to audit_logs (via sa_audit_signatures) so tampering
    | with a row after the fact is detectable. Used today for Super Admin
    | impersonation events; also used by the auth-event audit trail.
    |
    */
    'hmac_key' => env('AUDIT_LOG_HMAC_KEY', ''),

];
