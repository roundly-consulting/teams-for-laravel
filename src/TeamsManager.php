<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Closure;
use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Teams\Actions\CreateTeamAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Handles\Invites;
use RoundlyConsulting\Teams\Handles\JoinRequests;
use RoundlyConsulting\Teams\Handles\Members;
use RoundlyConsulting\Teams\Handles\TeamHandle;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\PermissionRegistry;

/**
 * The teams API — the root behind the `Teams` facade, injectable by its own class.
 *
 * Thin by design: every operation resolves an action from the container and runs it
 * through {@see self::perform()}, so host overrides apply and `Teams::fake()` records
 * calls made through the facade, an injected manager, the handles and the model
 * methods alike.
 *
 * Deliberately not final: `Testing\TeamsFake` extends it, so constructor-injected
 * managers receive the fake under `Teams::fake()`.
 */
class TeamsManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Create a team. An owner, when given, joins with the configured owner role.
     */
    public function create(CreateTeamData $data): Team
    {
        return $this->perform(
            TeamOperation::CreateTeam,
            CreateTeamAction::class,
            static fn (CreateTeamAction $action): Team => $action->execute($data),
            ['name' => $data->name, 'owner' => $data->owner],
        );
    }

    /**
     * A handle scoped to one team: members, invites, join requests, per-team roles,
     * ownership, settings, contacts, addresses and connections.
     */
    public function for(Team $team): TeamHandle
    {
        return new TeamHandle($this, $team);
    }

    /**
     * Invites across teams: accept one (by model or code), find one by code, prune
     * long-expired ones.
     */
    public function invites(): Invites
    {
        return new Invites($this);
    }

    /**
     * Memberships across teams: report or notify those expiring soon, prune long-expired ones.
     */
    public function members(): Members
    {
        return new Members($this);
    }

    /**
     * Join requests across teams: auto-decline expired pending requests.
     */
    public function joinRequests(): JoinRequests
    {
        return new JoinRequests($this);
    }

    /**
     * The global role vocabulary (`register`, `find`, `all`), backed by the configured
     * provider (`teams.roles.provider`).
     */
    public function roles(): RoleProvider
    {
        return $this->container->make(RoleProvider::class);
    }

    /**
     * The permission registry (`register`, `group`, `find`, `all`, `fromRoles`).
     */
    public function permissions(): PermissionRegistry
    {
        return $this->container->make(PermissionRegistry::class);
    }

    /**
     * Run one state-changing operation. Every handle and model method funnels its
     * terminal call through here, resolving the action from the container, so host
     * overrides apply and the fake records the call.
     *
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
        return $execute($this->container->make($action));
    }
}
