<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Teams\Models\Team;

final class TeamOwnershipTransferred
{
    use Dispatchable;

    public function __construct(
        public Team $team,
        public ?Model $previousOwner,
        public Model $newOwner,
    ) {}
}
