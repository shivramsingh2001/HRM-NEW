<?php

namespace App\Services\Attendance;

/** An employee left out of a bulk attendance mark for a user-facing reason (e.g. no shift that day). */
class BulkMarkSkipped extends \RuntimeException {}
