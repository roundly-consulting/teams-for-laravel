<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\PendingDispatch;
use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\PendingConnection;
use RoundlyConsulting\Contacts\ContactBook;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Teams\Actions\MoveTeamAction;
use RoundlyConsulting\Teams\Actions\TransferOwnershipAction;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Settings\TeamSettings;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::for($team)` — everything scoped to one team. Every child handle refuses
 * rows (invites, join requests, memberships) that belong to another team.
 */
final readonly class TeamHandle
{
    public function __construct(
        private TeamsManager $manager,
        private Team $team,
    ) {}

    public function members(): TeamMembers
    {
        return new TeamMembers($this->manager, $this->team);
    }

    public function invites(): TeamInvites
    {
        return new TeamInvites($this->manager, $this->team);
    }

    public function joinRequests(): TeamJoinRequests
    {
        return new TeamJoinRequests($this->manager, $this->team);
    }

    public function roles(): TeamRoles
    {
        return new TeamRoles($this->manager, $this->team);
    }

    /**
     * Hand the team to a new owner. The new owner becomes a member with the owner
     * role; the previous owner, if any, is demoted to the admin role.
     */
    public function transferOwnershipTo(Model $owner): Team
    {
        return $this->manager->perform(
            TeamOperation::TransferOwnership,
            TransferOwnershipAction::class,
            fn (TransferOwnershipAction $action): Team => $action->execute($this->team, $owner),
            ['team' => $this->team, 'owner' => $owner],
        );
    }

    /**
     * Place the team under `$parent` (nested teams, `teams.nesting.enabled`). Rejects a
     * cycle and a subtree deeper than `teams.nesting.max_depth`.
     */
    public function moveUnder(Team $parent): Team
    {
        return $this->move($parent);
    }

    /**
     * Detach the team from its parent; it becomes a root team.
     */
    public function moveToRoot(): Team
    {
        return $this->move(null);
    }

    /**
     * Run `$callback` with this team as the current team, then restore the previous
     * context — `Teams::runAs($team, $callback)`.
     *
     * @template TResult
     *
     * @param  Closure(Team): TResult  $callback
     * @return (TResult is PendingDispatch ? null : TResult)
     */
    public function run(Closure $callback): mixed
    {
        return $this->manager->runAs($this->team, $callback);
    }

    /**
     * Typed access to the team's governance settings (join policy, seat cap, default
     * member role, require-approval switch), backed by the options package.
     */
    public function settings(): TeamSettings
    {
        return new TeamSettings($this->team);
    }

    /**
     * The team's contact book — `Contacts::for($team)` from the contacts package.
     */
    public function contacts(): ContactBook
    {
        return Contacts::for($this->team);
    }

    /**
     * The team's address book — `Addresses::for($team)` from the addresses package.
     */
    public function addresses(): AddressBook
    {
        return Addresses::for($this->team);
    }

    /**
     * Connections from this team — `Connections::from($team)` from the connections
     * package (`->to($partner)->connect()`, `->to($x)->disconnect()`, …).
     */
    public function connections(): PendingConnection
    {
        return Connections::from($this->team);
    }

    public function team(): Team
    {
        return $this->team;
    }

    private function move(?Team $parent): Team
    {
        return $this->manager->perform(
            TeamOperation::MoveTeam,
            MoveTeamAction::class,
            fn (MoveTeamAction $action): Team => $action->execute($this->team, $parent),
            ['team' => $this->team, 'parent' => $parent],
        );
    }
}
