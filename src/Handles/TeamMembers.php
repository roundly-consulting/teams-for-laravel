<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\Actions\ChangeMemberRoleAction;
use RoundlyConsulting\Teams\Actions\RemoveMemberAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Exceptions\MemberNotFoundException;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::for($team)->members()` — the team's roster.
 */
final readonly class TeamMembers
{
    public function __construct(
        private TeamsManager $manager,
        private Team $team,
    ) {}

    /**
     * Add a member. Idempotent: re-adding an existing member returns the existing
     * membership, with its role (and expiry, when given) updated.
     *
     * @param  array<string, mixed>  $meta
     */
    public function add(Model $member, string $role, array $meta = [], ?CarbonInterface $expiresAt = null): Member
    {
        return $this->manager->perform(
            TeamOperation::AddMember,
            AddMemberAction::class,
            fn (AddMemberAction $action): Member => $action->execute($this->team, new AddMemberData(
                member: $member,
                role: $role,
                meta: $meta,
                expiresAt: $expiresAt,
            )),
            ['team' => $this->team, 'member' => $member, 'role' => $role],
        );
    }

    /**
     * Remove a member. Returns false when the model is not a member.
     */
    public function remove(Model $member): bool
    {
        return $this->manager->perform(
            TeamOperation::RemoveMember,
            RemoveMemberAction::class,
            fn (RemoveMemberAction $action): bool => $action->execute($this->team, $member),
            ['team' => $this->team, 'member' => $member],
        );
    }

    /**
     * Change a member's role.
     *
     * @throws MemberNotFoundException when the model is not a member
     */
    public function changeRole(Model $member, string $role): Member
    {
        return $this->manager->perform(
            TeamOperation::ChangeRole,
            ChangeMemberRoleAction::class,
            fn (ChangeMemberRoleAction $action): Member => $action->execute($this->team, $member, $role),
            ['team' => $this->team, 'member' => $member, 'role' => $role],
        );
    }

    /**
     * Every membership of the team, expired ones included.
     *
     * @return Collection<int, Member>
     */
    public function all(): Collection
    {
        return $this->team->members()->get();
    }

    public function has(Model $member): bool
    {
        return $this->team->hasMember($member);
    }

    public function find(Model $member): ?Member
    {
        return $this->team->findMember($member);
    }
}
