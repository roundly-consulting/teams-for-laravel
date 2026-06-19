<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\Actions\CreateTeamAction;
use RoundlyConsulting\Teams\Actions\RequestToJoinAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\DataTransferObjects\RequestToJoinData;
use RoundlyConsulting\Teams\Exceptions\InviteNotFoundException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Permissions;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\Roles;
use RoundlyConsulting\Teams\Testing\TeamsFake;

class Teams
{
    public function __construct(
        protected readonly CreateTeamAction $createTeam,
        protected readonly AcceptInviteAction $acceptInvite,
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

    public function acceptInviteByCode(string $code, Model $user, ?string $email = null): Member
    {
        /** @var class-string<Invite> $model */
        $model = config('teams.models.invite', Invite::class);

        /** @var Invite|null $invite */
        $invite = $model::query()->where('code', $code)->first();

        if ($invite === null) {
            throw InviteNotFoundException::forCode($code);
        }

        return $this->acceptInvite($invite, new AcceptInviteData(
            member: $user,
            email: $email,
        ));
    }

    /** @param array<string, mixed> $meta */
    public function requestToJoin(
        Team $team,
        Model $requester,
        ?string $requestedRole = null,
        ?string $message = null,
        array $meta = [],
    ): JoinRequest {
        return app(RequestToJoinAction::class)->execute(new RequestToJoinData(
            team: $team,
            requester: $requester,
            requestedRole: $requestedRole,
            message: $message,
            meta: $meta,
        ));
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

    /** @return array<string, Permission> */
    public function permissions(): array
    {
        return Permissions::all();
    }

    public static function fake(): TeamsFake
    {
        $fake = new TeamsFake(
            app(CreateTeamAction::class),
            app(AcceptInviteAction::class),
        );

        app()->instance('teams', $fake);
        Facade::clearResolvedInstance('teams');

        return $fake;
    }
}
