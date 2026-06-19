<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\Actions\CreateTeamAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\Roles;

final class Teams
{
    public function __construct(
        private readonly CreateTeamAction $createTeam,
        private readonly AcceptInviteAction $acceptInvite,
    ) {}

    public function createTeam(CreateTeamData $data): Team
    {
        return $this->createTeam->execute($data);
    }

    public function for(Team $team): TeamBuilder
    {
        return new TeamBuilder($team);
    }

    public function acceptInvite(Invite $invite, AcceptInviteData $data): Member
    {
        return $this->acceptInvite->execute($invite, $data);
    }

    public function role(string $key): ?Role
    {
        return Roles::find($key);
    }

    /** @return array<string, Role> */
    public function roles(): array
    {
        return Roles::all();
    }
}
