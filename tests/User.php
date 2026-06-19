<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use RoundlyConsulting\Teams\Traits\BelongsToTeam;
use RoundlyConsulting\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use BelongsToTeam;
    use HasTeams;

    /** @var bool */
    public $timestamps = false;

    /** @var array<string> */
    protected $guarded = [];
}
