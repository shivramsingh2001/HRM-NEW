<?php

namespace App\Support;

use App\Models\User;

/**
 * Nobody approves their own request (leave, regularization, overtime, expense,
 * WFH / travel, loan, offboarding, any workflow step) — it goes to their
 * reporting head, HR or an admin. Only an admin (company owner, nobody above)
 * may still decide on their own.
 */
final class SelfApproval
{
    public const MESSAGE = "You can't approve your own request. It goes to your reporting head, HR or admin.";

    public static function blocks(User $actor, ?int $ownerUserId): bool
    {
        return $ownerUserId !== null && $ownerUserId === (int) $actor->id && $actor->role !== 'admin';
    }
}
