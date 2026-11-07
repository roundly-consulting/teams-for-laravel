<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use RoundlyConsulting\Teams\Models\Team;

class UuidTeam extends Team
{
    use HasVersion4Uuids;
}
