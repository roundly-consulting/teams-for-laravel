<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Events\InviteResent;
use RoundlyConsulting\Teams\Models\Invite;

final readonly class ResendInviteAction
{
    /**
     * Re-issue an invite in place: rotate the code and extend the expiry from
     * now, preserving the invite's identity, role, email and meta.
     */
    public function execute(Invite $invite): Invite
    {
        /** @var int $codeLength */
        $codeLength = config('teams.invites.code_length', 32);

        $invite->update([
            'code' => Str::random($codeLength),
            'expires_at' => now()->add(config('teams.invites.expires_after', '7 days')),
        ]);

        InviteResent::dispatch($invite);

        return $invite;
    }
}
