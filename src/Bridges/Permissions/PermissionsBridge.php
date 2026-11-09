<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Permissions\Concerns\HasRoles;
use RoundlyConsulting\Permissions\PermissionsManager;
use RoundlyConsulting\Permissions\Support\PermissionRegistrar;
use RoundlyConsulting\Teams\Bridges\Permissions\Listeners\ForgetEndedMembershipGrants;
use RoundlyConsulting\Teams\Events\MembershipExpired;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Support\TeamsConfig;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * The one place teams talks to permissions-for-laravel (`teams.permissions.bridge`).
 *
 * permissions-for-laravel is a `suggest`, never a `require`: nothing outside
 * `Bridges\Permissions` may name a `RoundlyConsulting\Permissions` class (pinned by the arch
 * test), and every entry point here checks {@see self::enabled()} first, so a host without the
 * package never loads one.
 *
 * What the bridge adds, all read at CHECK time:
 *
 * - **Union.** `Team::memberHasPermission()` / `memberHasRole()` also count what
 *   `Permissions::for($member)->in($teams)` grants, where `$teams` are the governing teams
 *   (the team, plus its ancestors when roles inherit down) in which the member holds an
 *   active membership right now. A removed, expired or not-yet-started membership makes its
 *   team's grants inert even before the cleanup listeners run.
 * - **Ambient scope.** Scope-less Gate checks (`can:` middleware, `@can('x')`) resolve in the
 *   current team for its active members (`Permissions::resolveScopeUsing()`).
 * - **Cleanup.** A removed, pruned or revived (lapsed, then re-added) membership forgets the
 *   member's grants in that team; a force-deleted team forgets every grant made in it.
 *
 * @internal
 */
final class PermissionsBridge
{
    /**
     * Whether the bridge is switched on and permissions-for-laravel is installed.
     */
    public static function enabled(): bool
    {
        return TeamsConfig::permissionsBridge() && self::installed();
    }

    public static function installed(): bool
    {
        return class_exists(PermissionsManager::class);
    }

    /**
     * Wire the bridge. Called by the service provider only while `teams.permissions.bridge`
     * is on — and then a missing package or switched-off assignments fail the boot, rather
     * than leaving a switch that is on and does nothing.
     *
     * @throws InvalidConfigurationException
     */
    public static function boot(): void
    {
        $installed = self::installed();
        $failure = self::misconfiguration($installed, $installed && PermissionRegistrar::assignmentsEnabled());

        if ($failure !== null) {
            throw $failure;
        }

        app(PermissionsManager::class)->resolveScopeUsing(
            static fn (Authorizable $user, string $ability): ?array => self::ambientScopes($user),
        );

        Event::listen(TeamMemberDeleted::class, [ForgetEndedMembershipGrants::class, 'removed']);
        Event::listen(TeamMemberAdded::class, [ForgetEndedMembershipGrants::class, 'revived']);
        Event::listen(MembershipExpired::class, [ForgetEndedMembershipGrants::class, 'pruned']);

        TeamModel::class()::forceDeleted(static function (Team $team): void {
            if (self::enabled()) {
                app(PermissionsManager::class)->forgetScope($team);
            }
        });
    }

    /**
     * Why the bridge cannot run, or null when it can.
     */
    public static function misconfiguration(bool $installed, bool $assignmentsEnabled): ?InvalidConfigurationException
    {
        return match (true) {
            ! $installed => new InvalidConfigurationException((string) trans('teams::errors.permissions_bridge_not_installed')),
            ! $assignmentsEnabled => new InvalidConfigurationException((string) trans('teams::errors.permissions_bridge_assignments_off')),
            default => null,
        };
    }

    /**
     * Whether permissions-for-laravel grants `$member` the permission in `$team` right now.
     */
    public static function memberHasPermission(Team $team, Model $member, string $permission): bool
    {
        $scopes = self::scopesFor($team, $member);

        return $scopes !== [] && app(PermissionsManager::class)->for($member)->in($scopes)->hasPermissionTo($permission);
    }

    /**
     * Whether permissions-for-laravel gives `$member` the role in `$team` right now.
     */
    public static function memberHasRole(Team $team, Model $member, string $role): bool
    {
        $scopes = self::scopesFor($team, $member);

        return $scopes !== [] && app(PermissionsManager::class)->for($member)->in($scopes)->hasRole($role);
    }

    /**
     * The teams whose grants count for `$member` in `$team`: the governing teams (the team,
     * then its ancestors when roles inherit down) in which the member holds an active
     * membership right now. Empty for a holder without permissions' `HasRoles` (it can hold
     * no scoped grant) and for an unsaved team.
     *
     * @return list<Team>
     */
    public static function scopesFor(Team $team, Model $member): array
    {
        if (! $team->exists || ! self::holdsGrants($member)) {
            return [];
        }

        /** @var Collection<int, Team> $governing */
        $governing = TeamsConfig::inheritRolesDown()
            ? $team->ancestors()->prepend($team)
            : new Collection([$team]);

        $active = MemberModel::query()
            ->active()
            ->whereIn('team_id', $governing->map(static fn (Team $governed): mixed => $governed->getKey())->all())
            ->whereMorphedTo('member', $member)
            ->pluck('team_id')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        return array_values($governing
            ->filter(static fn (Team $governed): bool => in_array((string) $governed->getKey(), $active, true))
            ->all());
    }

    /**
     * Whether the model (or model class) can hold scoped grants at all (permissions' `HasRoles`).
     *
     * @param  Model|class-string  $member
     */
    public static function holdsGrants(Model|string $member): bool
    {
        return in_array(HasRoles::class, class_uses_recursive($member), true);
    }

    /**
     * The ambient scope of a scope-less Gate check: the current team (and the governing
     * teams above it when roles inherit down) for its active members. Null — an unscoped
     * check, where no team grant counts — without a single current team (none, or
     * multi-team mode), for a non-member and for a holder without `HasRoles`.
     *
     * @return list<Team>|null
     */
    public static function ambientScopes(Authorizable $user): ?array
    {
        if (! self::enabled() || ! $user instanceof Model) {
            return null;
        }

        $team = app(TeamsManager::class)->current();

        if ($team === null) {
            return null;
        }

        $scopes = self::scopesFor($team, $user);

        return $scopes === [] ? null : $scopes;
    }
}
