<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Teams\Models\JoinRequest;

final class JoinRequestDenied
{
    use Dispatchable;

    public function __construct(public JoinRequest $joinRequest) {}
}
