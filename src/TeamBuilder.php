<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\Actions\ApproveJoinRequestAction;
use RoundlyConsulting\Teams\Actions\ChangeMemberRoleAction;
use RoundlyConsulting\Teams\Actions\CreateInviteAction;
use RoundlyConsulting\Teams\Actions\DefineTeamRoleAction;
use RoundlyConsulting\Teams\Actions\DenyJoinRequestAction;
use RoundlyConsulting\Teams\Actions\RemoveMemberAction;
use RoundlyConsulting\Teams\Actions\TransferOwnershipAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;

final class TeamBuilder
{
    public function __construct(
        private readonly Team $team,
    ) {}

    /** @param array<string, mixed> $meta */
    public function addMember(Model $member, string $role, array $meta = [], ?CarbonInterface $expiresAt = null): self
    {
        app(AddMemberAction::class)->execute($this->team, new AddMemberData(
            member: $member,
            role: $role,
            meta: $meta,
            expiresAt: $expiresAt,
        ));

        return $this;
    }

    public function removeMember(Model $member): self
    {
        app(RemoveMemberAction::class)->execute($this->team, $member);

        return $this;
    }

    public function changeRole(Model $member, string $role): self
    {
        $found = $this->team->findMember($member);

        if ($found !== null) {
            app(ChangeMemberRoleAction::class)->execute($found, $role);
        }

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function invite(
        string $role,
        ?CarbonInterface $expiresAt = null,
        ?string $email = null,
        ?Model $invitedBy = null,
        array $meta = [],
        ?int $maxUses = 1,
    ): Invite {
        return app(CreateInviteAction::class)->execute($this->team, new CreateInviteData(
            role: $role,
            expiresAt: $expiresAt,
            email: $email,
            invitedBy: $invitedBy,
            meta: $meta,
            maxUses: $maxUses,
        ));
    }

    /** @param list<string|Permission> $permissions */
    public function defineRole(string $key, string $name, array $permissions = [], string $description = ''): TeamRole
    {
        return app(DefineTeamRoleAction::class)->execute(new DefineTeamRoleData(
            teamId: (int) $this->team->getKey(),
            key: $key,
            name: $name,
            permissions: $permissions,
            description: $description,
        ));
    }

    public function approveJoinRequest(JoinRequest $request, Model $responder, ?string $role = null): Member
    {
        return app(ApproveJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(
            responder: $responder,
            role: $role,
        ));
    }

    public function denyJoinRequest(JoinRequest $request, Model $responder): JoinRequest
    {
        return app(DenyJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(
            responder: $responder,
        ));
    }

    public function transferOwnershipTo(Model $owner): self
    {
        app(TransferOwnershipAction::class)->execute($this->team, $owner);

        return $this;
    }

    public function team(): Team
    {
        return $this->team;
    }
}
