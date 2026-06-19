<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\TeamBuilder;

/**
 * @method static Team createTeam(CreateTeamData $data)
 * @method static TeamBuilder for(Team $team)
 * @method static Member acceptInvite(Invite $invite, AcceptInviteData $data)
 * @method static Role|null role(string $key)
 * @method static array<string, Role> roles()
 *
 * @see \RoundlyConsulting\Teams\Teams
 */
final class Teams extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'teams';
    }
}
