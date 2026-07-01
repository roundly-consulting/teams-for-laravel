<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;
use RoundlyConsulting\Connections\Concerns\HasConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Teams\Traits\BelongsToTeam;
use RoundlyConsulting\Teams\Traits\HasTeams;

class User extends Authenticatable implements Connectable, GivesApprovalsInterface
{
    use BelongsToTeam;
    use GivesApprovals;
    use HasConnections;
    use HasTeams;

    /** @var bool */
    public $timestamps = false;

    /** @var array<string> */
    protected $guarded = [];
}
