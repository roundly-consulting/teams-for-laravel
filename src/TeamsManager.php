<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\PendingDispatch;
use RoundlyConsulting\Teams\Actions\CreateTeamAction;
use RoundlyConsulting\Teams\Actions\InspectTeamScopesAction;
use RoundlyConsulting\Teams\Actions\RunAsTeamAction;
use RoundlyConsulting\Teams\Actions\SetCurrentTeamAction;
use RoundlyConsulting\Teams\Actions\SetCurrentTeamsAction;
use RoundlyConsulting\Teams\Actions\SwitchTeamAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\DataTransferObjects\ScopedModelReport;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Handles\Invites;
use RoundlyConsulting\Teams\Handles\JoinRequests;
use RoundlyConsulting\Teams\Handles\Members;
use RoundlyConsulting\Teams\Handles\TeamHandle;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\PermissionRegistry;
use RoundlyConsulting\Teams\Scoping\TeamContext;

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
     * The current team, or null when no context is set or in multi-team mode.
     */
    public function current(): ?Team
    {
        return $this->context()->current();
    }

    /**
     * Every team in context: the current team, the multi-team set, or none.
     *
     * @return Collection<int, Team>
     */
    public function currentMany(): Collection
    {
        return $this->context()->teams();
    }

    /**
     * Make `$team` the current team (null clears the context). Team-scoped models then
     * read and write only its rows; jobs dispatched from here on carry it along.
     *
     * UNCHECKED — no membership check. For system code acting for a team (a webhook, a
     * command, a seeder); for a team a user picked, use {@see self::switchTo()}.
     */
    public function setCurrent(?Team $team): void
    {
        $this->container->make(SetCurrentTeamAction::class)->execute($team);
    }

    /**
     * The CHECKED `setCurrent()`: make `$team` the current team only when `$user` — by
     * default the authenticated user — may act in it (an active membership, or one on a
     * team above with roles inheriting down); otherwise throw and leave the context alone.
     * The context remembers `$user`, so queued jobs can re-check it
     * (`teams.context.recheck_queued_jobs`).
     *
     * @throws TeamContextException
     */
    public function switchTo(Team $team, ?Model $user = null): void
    {
        $this->container->make(SwitchTeamAction::class)->execute($team, $user);
    }

    /**
     * Multi-team mode ("all my teams"): scoped models read every given team's rows, and a
     * write must name one of them. Each team must be one `$user` — by default the
     * authenticated user — holds an active membership of; otherwise this throws.
     *
     * @param  iterable<Team>  $teams
     */
    public function setCurrentMany(iterable $teams, ?Model $user = null): void
    {
        $this->container->make(SetCurrentTeamsAction::class)->execute($teams, $user);
    }

    /**
     * Run `$callback` as `$team`, then restore the previous context, also on an exception.
     *
     * @template TResult
     *
     * @param  Closure(Team): TResult  $callback
     * @return (TResult is PendingDispatch ? null : TResult)
     */
    public function runAs(Team $team, Closure $callback): mixed
    {
        return $this->container->make(RunAsTeamAction::class)->execute($team, $callback);
    }

    /**
     * Run `$callback` with team scoping switched off: scoped reads see every team's rows
     * and scoped writes go unchecked. For admin tooling and seeders — keep it greppable.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function withoutTeamScope(Closure $callback): mixed
    {
        return $this->context()->bypass($callback);
    }

    /**
     * The doctor: every team-scoped model, with whether its table has the team column
     * and an index on it (`php artisan teams:doctor`).
     *
     * @return list<ScopedModelReport>
     */
    public function scopedModels(): array
    {
        return $this->container->make(InspectTeamScopesAction::class)->execute();
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

    private function context(): TeamContext
    {
        return $this->container->make(TeamContext::class);
    }
}
