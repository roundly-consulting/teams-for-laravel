<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Teams\Models\Invite;

final class InviteResent
{
    use Dispatchable;

    public function __construct(public Invite $invite) {}
}
