<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Events\InviteResent;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Support\TeamsConfig;

final readonly class ResendInviteAction
{
    /**
     * Re-issue an invite in place: rotate the code and extend the expiry from
     * now, preserving the invite's identity, role, email and meta.
     */
    public function execute(Invite $invite): Invite
    {
        $invite->update([
            'code' => Str::random(TeamsConfig::inviteCodeLength()),
            'expires_at' => now()->add(TeamsConfig::inviteExpiresAfter()),
        ]);

        InviteResent::dispatch($invite);

        return $invite;
    }
}
