<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Events\InviteRevoked;
use RoundlyConsulting\Teams\Models\Invite;

final readonly class RevokeInviteAction
{
    public function execute(Invite $invite): bool
    {
        $revoked = (bool) $invite->delete();

        if ($revoked) {
            InviteRevoked::dispatch($invite);
        }

        return $revoked;
    }
}
