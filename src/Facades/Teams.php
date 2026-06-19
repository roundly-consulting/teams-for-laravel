<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\TeamBuilder;
use RoundlyConsulting\Teams\Testing\TeamsFake;

/**
 * @method static Team createTeam(CreateTeamData $data)
 * @method static TeamBuilder for(Team $team)
 * @method static Member acceptInvite(Invite $invite, AcceptInviteData $data)
 * @method static Member acceptInviteByCode(string $code, Model $user, ?string $email = null)
 * @method static JoinRequest requestToJoin(Team $team, Model $requester, ?string $requestedRole = null, ?string $message = null, array<string, mixed> $meta = [])
 * @method static Role|null role(string $key)
 * @method static array<string, Role> roles()
 * @method static array<string, Permission> permissions()
 * @method static TeamsFake fake()
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
