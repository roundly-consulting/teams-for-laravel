<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Models\Invite;

final class PruneInvitesAction
{
    /**
     * Force-delete invites that expired more than a month ago.
     */
    public function execute(): int
    {
        /** @var class-string<Invite> $model */
        $model = config('teams.models.invite', Invite::class);

        return $model::query()
            ->where('expires_at', '<=', now()->subMonth())
            ->forceDelete();
    }
}
