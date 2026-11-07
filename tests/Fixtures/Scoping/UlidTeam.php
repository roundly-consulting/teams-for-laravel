<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use RoundlyConsulting\Teams\Models\Team;

/**
 * A host that keys its teams by ULID through a `teams.models.team` subclass.
 */
class UlidTeam extends Team
{
    use HasUlids;
}
