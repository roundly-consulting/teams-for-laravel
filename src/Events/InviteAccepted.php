<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;

final class InviteAccepted
{
    use Dispatchable;

    public function __construct(
        public Invite $invite,
        public Member $member,
    ) {}
}
