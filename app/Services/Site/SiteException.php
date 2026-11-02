<?php

namespace App\Services\Site;

use RuntimeException;

/** A site change that can't go ahead — the message is shown to the user as-is. */
class SiteException extends RuntimeException
{
}
