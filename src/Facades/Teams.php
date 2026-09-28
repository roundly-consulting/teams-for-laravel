<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Testing\TeamsFake;

/**
 * @method static \RoundlyConsulting\Teams\Models\Team create(\RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData $data)
 * @method static \RoundlyConsulting\Teams\Handles\TeamHandle for(\RoundlyConsulting\Teams\Models\Team $team)
 * @method static \RoundlyConsulting\Teams\Handles\Invites invites()
 * @method static \RoundlyConsulting\Teams\Handles\Members members()
 * @method static \RoundlyConsulting\Teams\Handles\JoinRequests joinRequests()
 * @method static \RoundlyConsulting\Teams\Roles\Contracts\RoleProvider roles()
 * @method static \RoundlyConsulting\Teams\Roles\PermissionRegistry permissions()
 * @method static list<\RoundlyConsulting\Teams\Testing\RecordedTeamOperation> recorded(?\RoundlyConsulting\Teams\Enums\TeamOperation $operation = null)
 * @method static void assertNothingRecorded()
 * @method static void assertTeamCreated(?string $name = null)
 * @method static void assertNothingCreated()
 * @method static void assertOwnershipTransferred(\RoundlyConsulting\Teams\Models\Team $team, ?\Illuminate\Database\Eloquent\Model $to = null)
 * @method static void assertNothingTransferred()
 * @method static void assertMemberAdded(\RoundlyConsulting\Teams\Models\Team $team, \Illuminate\Database\Eloquent\Model $member, ?string $role = null)
 * @method static void assertMemberNotAdded(\RoundlyConsulting\Teams\Models\Team $team, \Illuminate\Database\Eloquent\Model $member)
 * @method static void assertNothingAdded()
 * @method static void assertMemberRemoved(\RoundlyConsulting\Teams\Models\Team $team, \Illuminate\Database\Eloquent\Model $member)
 * @method static void assertNothingRemoved()
 * @method static void assertRoleChanged(\RoundlyConsulting\Teams\Models\Team $team, \Illuminate\Database\Eloquent\Model $member, ?string $role = null)
 * @method static void assertNoRoleChanged()
 * @method static void assertExpiringMembersNotified()
 * @method static void assertNothingNotified()
 * @method static void assertInviteCreated(\RoundlyConsulting\Teams\Models\Team $team, ?string $email = null)
 * @method static void assertNothingInvited()
 * @method static void assertInviteResent(\RoundlyConsulting\Teams\Models\Invite $invite)
 * @method static void assertNothingResent()
 * @method static void assertInviteRevoked(\RoundlyConsulting\Teams\Models\Invite $invite)
 * @method static void assertNothingRevoked()
 * @method static void assertInviteAccepted(?\RoundlyConsulting\Teams\Models\Invite $invite = null, ?\Illuminate\Database\Eloquent\Model $by = null)
 * @method static void assertNothingAccepted()
 * @method static void assertJoinRequested(\RoundlyConsulting\Teams\Models\Team $team, ?\Illuminate\Database\Eloquent\Model $requester = null)
 * @method static void assertNothingRequested()
 * @method static void assertJoinRequestApproved(\RoundlyConsulting\Teams\Models\JoinRequest $request, ?\Illuminate\Database\Eloquent\Model $by = null)
 * @method static void assertNothingApproved()
 * @method static void assertJoinRequestDenied(\RoundlyConsulting\Teams\Models\JoinRequest $request, ?\Illuminate\Database\Eloquent\Model $by = null)
 * @method static void assertNothingDenied()
 * @method static void assertRoleDefined(\RoundlyConsulting\Teams\Models\Team $team, ?string $key = null)
 * @method static void assertNothingDefined()
 * @method static void assertInvitesPruned()
 * @method static void assertMembersPruned()
 * @method static void assertJoinRequestsExpired()
 * @method static void assertNothingPruned()
 *
 * The `assert*` / `recorded` methods exist only on the fake — call `Teams::fake()` first.
 *
 * @see TeamsManager
 * @see TeamsFake
 */
final class Teams extends Facade
{
    /**
     * Swap in a recording fake. Operations still run against the database; the fake
     * records each one — through the facade, injected managers, the handles and the
     * model methods — for assertions.
     */
    public static function fake(): TeamsFake
    {
        $fake = app(TeamsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return TeamsManager::class;
    }
}
