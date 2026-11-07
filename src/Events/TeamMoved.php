<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Teams\Models\Team;

/**
 * A team was placed under a new parent, or detached to become a root (`$parent` null).
 */
final class TeamMoved
{
    use Dispatchable;

    public function __construct(
        public Team $team,
        public ?Team $parent,
        public int|string|null $previousParentKey,
    ) {}
}
