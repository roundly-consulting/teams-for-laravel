<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Teams\Models\Member;

final class MembershipExpired
{
    use Dispatchable;

    public function __construct(public Member $member) {}
}
