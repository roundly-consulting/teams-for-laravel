<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Support\InviteModel;

final class PruneInvitesAction
{
    /**
     * Force-delete invites that expired more than a month ago.
     */
    public function execute(): int
    {
        return InviteModel::query()
            ->where('expires_at', '<=', now()->subMonth())
            ->forceDelete();
    }
}
