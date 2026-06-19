<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\Actions\ChangeMemberRoleAction;
use RoundlyConsulting\Teams\Actions\CreateInviteAction;
use RoundlyConsulting\Teams\Actions\RemoveMemberAction;
use RoundlyConsulting\Teams\Actions\TransferOwnershipAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;

final class TeamBuilder
{
    public function __construct(
        private readonly Team $team,
    ) {}

    /** @param array<string, mixed> $meta */
    public function addMember(Model $member, string $role, array $meta = []): self
    {
        app(AddMemberAction::class)->execute($this->team, new AddMemberData(
            member: $member,
            role: $role,
            meta: $meta,
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
    ): Invite {
        return app(CreateInviteAction::class)->execute($this->team, new CreateInviteData(
            role: $role,
            expiresAt: $expiresAt,
            email: $email,
            invitedBy: $invitedBy,
            meta: $meta,
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
