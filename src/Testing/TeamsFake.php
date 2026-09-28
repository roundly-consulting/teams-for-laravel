<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * A recording, still-performing {@see TeamsManager} for host tests, swapped in by
 * `Teams::fake()`. Every operation still runs against the database; the fake
 * records each one — made through the facade, an injected manager, the handles, the
 * model methods (`$team->addMember()`, `$invite->revoke()`, …) or the commands — so a
 * test can assert on what was asked for.
 *
 * Only operations are recorded, not their internal side effects: accepting an invite
 * records `AcceptInvite`, not the `AddMember` the action performs inside.
 */
final class TeamsFake extends TeamsManager
{
    /** @var list<RecordedTeamOperation> */
    private array $recorded = [];

    /**
     * @internal
     *
     * @template TAction of object
     * @template TResult
     *
     * @param  class-string<TAction>  $action
     * @param  Closure(TAction): TResult  $execute
     * @param  array<string, mixed>  $context
     * @return TResult
     */
    public function perform(TeamOperation $operation, string $action, Closure $execute, array $context = []): mixed
    {
        $result = parent::perform($operation, $action, $execute, $context);

        $this->recorded[] = new RecordedTeamOperation($operation, $context, $result);

        return $result;
    }

    /**
     * Every recorded operation, optionally only one kind, in call order.
     *
     * @return list<RecordedTeamOperation>
     */
    public function recorded(?TeamOperation $operation = null): array
    {
        if ($operation === null) {
            return $this->recorded;
        }

        return array_values(array_filter(
            $this->recorded,
            static fn (RecordedTeamOperation $recorded): bool => $recorded->operation === $operation,
        ));
    }

    public function assertNothingRecorded(): void
    {
        Assert::assertEmpty(
            $this->recorded,
            sprintf('Expected no team operations, but %d were recorded.', count($this->recorded)),
        );
    }

    // Teams ------------------------------------------------------------------------

    public function assertTeamCreated(?string $name = null): void
    {
        $this->assertRecorded(
            TeamOperation::CreateTeam,
            static fn (RecordedTeamOperation $op): bool => $name === null || $op->string('name') === $name,
            $name === null ? 'Expected a team to be created, but none were.' : "Expected a team named [{$name}] to be created.",
        );
    }

    public function assertNothingCreated(): void
    {
        $this->assertNone([TeamOperation::CreateTeam], 'Expected no teams to be created, but %d were.');
    }

    public function assertOwnershipTransferred(Team $team, ?Model $to = null): void
    {
        $this->assertRecorded(
            TeamOperation::TransferOwnership,
            static fn (RecordedTeamOperation $op): bool => $op->involves('team', $team)
                && ($to === null || $op->involves('owner', $to)),
            'Expected the team ownership to be transferred'.($to === null ? '.' : ' to the given model.'),
        );
    }

    public function assertNothingTransferred(): void
    {
        $this->assertNone([TeamOperation::TransferOwnership], 'Expected no ownership transfer, but %d were recorded.');
    }

    // Members ----------------------------------------------------------------------

    public function assertMemberAdded(Team $team, Model $member, ?string $role = null): void
    {
        $this->assertRecorded(
            TeamOperation::AddMember,
            $this->memberOperation($team, $member, $role),
            'Expected the model to be added to the team'.($role === null ? '.' : " as [{$role}]."),
        );
    }

    public function assertMemberNotAdded(Team $team, Model $member): void
    {
        Assert::assertEmpty(
            $this->matching(TeamOperation::AddMember, $this->memberOperation($team, $member)),
            'Expected the model not to be added to the team, but it was.',
        );
    }

    public function assertNothingAdded(): void
    {
        $this->assertNone([TeamOperation::AddMember], 'Expected no members to be added, but %d were.');
    }

    public function assertMemberRemoved(Team $team, Model $member): void
    {
        $this->assertRecorded(
            TeamOperation::RemoveMember,
            $this->memberOperation($team, $member),
            'Expected the model to be removed from the team, but it was not.',
        );
    }

    public function assertNothingRemoved(): void
    {
        $this->assertNone([TeamOperation::RemoveMember], 'Expected no members to be removed, but %d were.');
    }

    public function assertRoleChanged(Team $team, Model $member, ?string $role = null): void
    {
        $this->assertRecorded(
            TeamOperation::ChangeRole,
            $this->memberOperation($team, $member, $role),
            'Expected the member\'s role to be changed'.($role === null ? '.' : " to [{$role}]."),
        );
    }

    public function assertNoRoleChanged(): void
    {
        $this->assertNone([TeamOperation::ChangeRole], 'Expected no role changes, but %d were recorded.');
    }

    public function assertExpiringMembersNotified(): void
    {
        $this->assertRecorded(
            TeamOperation::NotifyExpiringMembers,
            static fn (): bool => true,
            'Expected expiring memberships to be notified, but they were not.',
        );
    }

    public function assertNothingNotified(): void
    {
        $this->assertNone([TeamOperation::NotifyExpiringMembers], 'Expected no expiry notifications, but %d runs were recorded.');
    }

    // Invites ----------------------------------------------------------------------

    public function assertInviteCreated(Team $team, ?string $email = null): void
    {
        $this->assertRecorded(
            TeamOperation::CreateInvite,
            static fn (RecordedTeamOperation $op): bool => $op->involves('team', $team)
                && ($email === null || $op->string('email') === $email),
            'Expected an invite to be created for the team'.($email === null ? '.' : " addressed to [{$email}]."),
        );
    }

    public function assertNothingInvited(): void
    {
        $this->assertNone([TeamOperation::CreateInvite], 'Expected no invites to be created, but %d were.');
    }

    public function assertInviteResent(Invite $invite): void
    {
        $this->assertRecorded(
            TeamOperation::ResendInvite,
            static fn (RecordedTeamOperation $op): bool => $op->involves('invite', $invite),
            'Expected the invite to be resent, but it was not.',
        );
    }

    public function assertNothingResent(): void
    {
        $this->assertNone([TeamOperation::ResendInvite], 'Expected no invites to be resent, but %d were.');
    }

    public function assertInviteRevoked(Invite $invite): void
    {
        $this->assertRecorded(
            TeamOperation::RevokeInvite,
            static fn (RecordedTeamOperation $op): bool => $op->involves('invite', $invite),
            'Expected the invite to be revoked, but it was not.',
        );
    }

    public function assertNothingRevoked(): void
    {
        $this->assertNone([TeamOperation::RevokeInvite], 'Expected no invites to be revoked, but %d were.');
    }

    public function assertInviteAccepted(?Invite $invite = null, ?Model $by = null): void
    {
        $this->assertRecorded(
            TeamOperation::AcceptInvite,
            static fn (RecordedTeamOperation $op): bool => ($invite === null || $op->involves('invite', $invite))
                && ($by === null || $op->involves('member', $by)),
            'Expected the invite to be accepted, but it was not.',
        );
    }

    public function assertNothingAccepted(): void
    {
        $this->assertNone([TeamOperation::AcceptInvite], 'Expected no invites to be accepted, but %d were.');
    }

    // Join requests ----------------------------------------------------------------

    public function assertJoinRequested(Team $team, ?Model $requester = null): void
    {
        $this->assertRecorded(
            TeamOperation::RequestToJoin,
            static fn (RecordedTeamOperation $op): bool => $op->involves('team', $team)
                && ($requester === null || $op->involves('requester', $requester)),
            'Expected a request to join the team, but none was recorded.',
        );
    }

    public function assertNothingRequested(): void
    {
        $this->assertNone([TeamOperation::RequestToJoin], 'Expected no join requests, but %d were recorded.');
    }

    public function assertJoinRequestApproved(JoinRequest $request, ?Model $by = null): void
    {
        $this->assertRecorded(
            TeamOperation::ApproveJoinRequest,
            $this->responseOperation($request, $by),
            'Expected the join request to be approved, but it was not.',
        );
    }

    public function assertNothingApproved(): void
    {
        $this->assertNone([TeamOperation::ApproveJoinRequest], 'Expected no join requests to be approved, but %d were.');
    }

    public function assertJoinRequestDenied(JoinRequest $request, ?Model $by = null): void
    {
        $this->assertRecorded(
            TeamOperation::DenyJoinRequest,
            $this->responseOperation($request, $by),
            'Expected the join request to be denied, but it was not.',
        );
    }

    public function assertNothingDenied(): void
    {
        $this->assertNone([TeamOperation::DenyJoinRequest], 'Expected no join requests to be denied, but %d were.');
    }

    // Roles ------------------------------------------------------------------------

    public function assertRoleDefined(Team $team, ?string $key = null): void
    {
        $this->assertRecorded(
            TeamOperation::DefineRole,
            static fn (RecordedTeamOperation $op): bool => $op->involves('team', $team)
                && ($key === null || $op->string('key') === $key),
            'Expected a role to be defined for the team'.($key === null ? '.' : " under [{$key}]."),
        );
    }

    public function assertNothingDefined(): void
    {
        $this->assertNone([TeamOperation::DefineRole], 'Expected no per-team roles to be defined, but %d were.');
    }

    // Housekeeping -----------------------------------------------------------------

    public function assertInvitesPruned(): void
    {
        $this->assertRecorded(TeamOperation::PruneInvites, static fn (): bool => true, 'Expected invites to be pruned, but they were not.');
    }

    public function assertMembersPruned(): void
    {
        $this->assertRecorded(TeamOperation::PruneMembers, static fn (): bool => true, 'Expected memberships to be pruned, but they were not.');
    }

    public function assertJoinRequestsExpired(): void
    {
        $this->assertRecorded(TeamOperation::ExpireJoinRequests, static fn (): bool => true, 'Expected join requests to be expired, but they were not.');
    }

    /**
     * No invite prune, member prune or join-request expiry ran.
     */
    public function assertNothingPruned(): void
    {
        $this->assertNone(
            [TeamOperation::PruneInvites, TeamOperation::PruneMembers, TeamOperation::ExpireJoinRequests],
            'Expected no pruning, but %d runs were recorded.',
        );
    }

    /**
     * @return Closure(RecordedTeamOperation): bool
     */
    private function memberOperation(Team $team, Model $member, ?string $role = null): Closure
    {
        return static fn (RecordedTeamOperation $op): bool => $op->involves('team', $team)
            && $op->involves('member', $member)
            && ($role === null || $op->string('role') === $role);
    }

    /**
     * @return Closure(RecordedTeamOperation): bool
     */
    private function responseOperation(JoinRequest $request, ?Model $by): Closure
    {
        return static fn (RecordedTeamOperation $op): bool => $op->involves('request', $request)
            && ($by === null || $op->involves('by', $by));
    }

    /**
     * @param  Closure(RecordedTeamOperation): bool  $filter
     * @return list<RecordedTeamOperation>
     */
    private function matching(TeamOperation $operation, Closure $filter): array
    {
        return array_values(array_filter($this->recorded($operation), $filter));
    }

    /**
     * @param  Closure(RecordedTeamOperation): bool  $filter
     */
    private function assertRecorded(TeamOperation $operation, Closure $filter, string $message): void
    {
        Assert::assertNotEmpty($this->matching($operation, $filter), $message);
    }

    /**
     * @param  list<TeamOperation>  $operations
     */
    private function assertNone(array $operations, string $message): void
    {
        $count = count(array_filter(
            $this->recorded,
            static fn (RecordedTeamOperation $recorded): bool => in_array($recorded->operation, $operations, true),
        ));

        Assert::assertSame(0, $count, sprintf($message, $count));
    }
}
