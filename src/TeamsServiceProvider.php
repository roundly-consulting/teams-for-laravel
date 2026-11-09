<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Log\Context\Events\ContextHydrated;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBladeDirectives;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Teams\Actions\RecheckTeamContextAction;
use RoundlyConsulting\Teams\Bridges\Permissions\PermissionsBridge;
use RoundlyConsulting\Teams\Commands\DoctorCommand;
use RoundlyConsulting\Teams\Commands\ListPermissionsCommand;
use RoundlyConsulting\Teams\Commands\ListRolesCommand;
use RoundlyConsulting\Teams\Commands\MakePolicyCommand;
use RoundlyConsulting\Teams\Commands\MembersExpiringCommand;
use RoundlyConsulting\Teams\Commands\PruneInvitesCommand;
use RoundlyConsulting\Teams\Commands\PruneJoinRequestsCommand;
use RoundlyConsulting\Teams\Commands\PruneMembersCommand;
use RoundlyConsulting\Teams\Commands\ResendInviteCommand;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Http\Middleware\SetTeamContext;
use RoundlyConsulting\Teams\Listeners\SyncJoinRequestStatusFromApproval;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;
use RoundlyConsulting\Teams\Roles\PermissionRegistry;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\Scoping\TeamScopeColumn;
use RoundlyConsulting\Teams\Support\InviteModel;
use RoundlyConsulting\Teams\Support\JoinRequestModel;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Support\TeamRoleModel;
use RoundlyConsulting\Teams\Support\TeamsConfig;

final class TeamsServiceProvider extends PackageServiceProvider
{
    use RegistersBladeDirectives;
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('teams')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasTranslations()
            ->hasCommands([
                ListRolesCommand::class,
                ListPermissionsCommand::class,
                PruneInvitesCommand::class,
                PruneMembersCommand::class,
                PruneJoinRequestsCommand::class,
                MembersExpiringCommand::class,
                ResendInviteCommand::class,
                MakePolicyCommand::class,
                DoctorCommand::class,
            ])
            ->publishesStubs(
                __DIR__.'/../stubs/AcceptInviteController.stub',
                app_path('Http/Controllers/AcceptInviteController.php'),
                'teams-stubs',
            )
            ->publishesStubs(
                __DIR__.'/../stubs/teams-routes.stub',
                base_path('routes/teams.php'),
                'teams-stubs',
            )
            ->publishesStubs(
                __DIR__.'/../stubs/TeamEventSubscriber.stub',
                app_path('Listeners/TeamEventSubscriber.php'),
                'teams-stubs',
            )
            ->publishesStubs(
                __DIR__.'/../stubs/Pest.teams.stub',
                base_path('tests/Teams.php'),
                'teams-stubs',
            )
            ->contributesToAbout(fn (): array => $this->aboutSection());
    }

    public function register(): void
    {
        parent::register();

        // Code-defined roles are registered once at boot, so their registry lives for
        // the whole process.
        $this->app->singleton(InMemoryRoleProvider::class);

        // SCOPED, not a singleton: the database provider memoises the role map, and a
        // queue worker or Octane process outlives any single request. The container
        // forgets scoped instances before every job and request, so a permission
        // revoked by another process is honoured from the next one on.
        $this->app->scoped(RoleProvider::class, function (Application $app): RoleProvider {
            if (TeamsConfig::roleProvider() !== TeamsConfig::PROVIDER_DATABASE) {
                return $app->make(InMemoryRoleProvider::class);
            }

            // Behind the shared cache the database provider must not memoise: a cache
            // refill has to read the table, never this process's earlier copy of it.
            return Config::boolean('teams.roles.cache.enabled')
                ? new CachedRoleProvider(new DatabaseRoleProvider(memoize: false))
                : new DatabaseRoleProvider;
        });

        $this->app->singleton(TeamRoleResolver::class);
        $this->app->singleton(PermissionRegistry::class);

        $this->app->singleton(TeamsManager::class);

        // SCOPED like Laravel's own Context repository it fronts: the loaded team models
        // and the withoutTeamScope() bypass belong to one request or job, never the next.
        $this->app->scoped(TeamContext::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations' key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();
        $this->registerTeamScopeMacro();

        $this->registerGate();
        $this->registerContextMiddleware();
        $this->announceRestoredContext();

        Event::listen(ApprovalRequestResolved::class, SyncJoinRequestStatusFromApproval::class);

        // Optional: wired only when switched on, and then it fails the boot when
        // permissions-for-laravel (or its scoped assignments) is missing.
        if (TeamsConfig::permissionsBridge()) {
            PermissionsBridge::boot();
        }
    }

    /**
     * `$table->teamScope()` — the team column of a scoped table, typed like the team key,
     * indexed and constrained onto the teams table.
     */
    private function registerTeamScopeMacro(): void
    {
        if (Blueprint::hasMacro('teamScope')) {
            return;
        }

        Blueprint::macro('teamScope', function (string $column = 'team_id', bool $nullable = false): ForeignKeyDefinition {
            /** @var Blueprint $this */
            return TeamScopeColumn::add($this, $column, $nullable);
        });
    }

    /**
     * The `teams.context` alias, and its place in the priority list: right before
     * SubstituteBindings, so a team-scoped route model (`{ticket}`) is bound inside the
     * team context — bound outside it, the fail-closed scope would 404 every one. The
     * authentication middleware already sits earlier in that list, so the user is known.
     */
    private function registerContextMiddleware(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('teams.context', SetTeamContext::class);
        });

        $this->callAfterResolving(HttpKernelContract::class, static function (HttpKernelContract $kernel): void {
            if ($kernel instanceof HttpKernel) {
                $kernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, SetTeamContext::class);
            }
        });
    }

    /**
     * A queued job restores its dispatcher's team context through Laravel's Context
     * hydration; announce it like any other context change.
     *
     * With `teams.context.recheck_queued_jobs` on, the acting member is checked again first,
     * against the database as it is NOW — a membership removed or lapsed after dispatch is
     * caught. A failed check clears the context (fail closed) and is never announced; the
     * JobProcessing listener below then fails the job before it runs. That listener is
     * registered after the framework's own hydrating one, so it always sees the outcome.
     */
    private function announceRestoredContext(): void
    {
        Event::listen(ContextHydrated::class, function (ContextHydrated $event): void {
            $context = $this->app->make(TeamContext::class);

            // A revocation belongs to the hydration that found it, never to a later one.
            $context->pullRevocation();

            if (! $event->context->hasHidden(TeamContext::KEYS)) {
                return;
            }

            if (TeamsConfig::recheckQueuedJobs()) {
                try {
                    $this->app->make(RecheckTeamContextAction::class)->execute();
                } catch (TeamContextException $exception) {
                    $context->revoke($exception);

                    return;
                }
            }

            TeamContextSet::dispatch($context->teams(), $context->isMultiple(), true);
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $revocation = $this->app->make(TeamContext::class)->pullRevocation();

            if ($revocation === null) {
                return;
            }

            // Failed, not released: a revoked membership does not come back on a retry.
            $event->job->fail($revocation);

            throw $revocation;
        });
    }

    /**
     * A `Gate::before` HOOK, deliberately not the toolkit's `defineGate()`.
     *
     * The toolkit trait registers a NAMED ability with `Gate::define()`. This
     * package has no ability name to register: it pre-checks every ability the
     * host checks, answers the ones under its configured prefix from the team's
     * own role/permission set, and returns null for everything else so the host's
     * own policies still decide. A definition could not fall through.
     */
    private function registerGate(): void
    {
        if (! Config::boolean('teams.gate.register', true)) {
            return;
        }

        $prefix = TeamsConfig::gatePrefix();
        $ownerAbility = TeamsConfig::gateOwnerAbility();

        Gate::before(function (Model $user, string $ability, array $arguments) use ($prefix, $ownerAbility): ?bool {
            if (! Str::startsWith($ability, $prefix.'.')) {
                return null;
            }

            $team = $arguments[0] ?? null;

            if (! $team instanceof Team) {
                return null;
            }

            $permission = Str::after($ability, $prefix.'.');

            if ($permission === $ownerAbility) {
                return $team->isOwnedBy($user) ?: null;
            }

            // `?: null` — a "no" must fall through to the host's own policies, not deny.
            return $team->memberHasPermission($user, $permission) ?: null;
        });

        $this->registerBladeIf('teamPermission', function (Team $team, string $permission, ?Model $member = null): bool {
            $member ??= auth()->user();

            return $member instanceof Model && $team->memberHasPermission($member, $permission);
        });

        $this->registerBladeIf('teamRole', function (Team $team, string $role, ?Model $member = null): bool {
            $member ??= auth()->user();

            return $member instanceof Model && $team->memberHasRole($member, $role);
        });

        $this->registerBladeIf('teamOwner', function (Team $team, ?Model $member = null): bool {
            $member ??= auth()->user();

            return $member instanceof Model && $team->isOwnedBy($member);
        });
    }

    /**
     * A team's roles, permissions and gate abilities are the HOST's own
     * authorization vocabulary — a role key names a business function
     * ("series-c-signatory") and a permission names what it may do. So the
     * section reports models, switches, bounds and counts, and never a role key,
     * a permission, an ability prefix, a cache store or a queue connection.
     *
     * @return array<string, string>
     */
    private function aboutSection(): array
    {
        return [
            'Team model' => class_basename(TeamModel::class()),
            'Member model' => class_basename(MemberModel::class()),
            'Invite model' => class_basename(InviteModel::class()),
            'Team role model' => class_basename(TeamRoleModel::class()),
            'Join request model' => class_basename(JoinRequestModel::class()),
            'Role provider' => self::orInvalid(static fn (): string => TeamsConfig::roleProvider()),
            'Registered roles' => self::orInvalid(fn (): string => $this->countOf(count($this->app->make(RoleProvider::class)->all()), 'role')),
            'Registered permissions' => $this->countOf(count($this->app->make(PermissionRegistry::class)->all()), 'permission'),
            'Role keys' => $this->roleKeys(),
            'Per-team roles' => Config::boolean('teams.roles.per_team') ? 'ON' : 'OFF',
            'Role cache' => $this->roleCache(),
            'Invites' => self::orInvalid(static fn (): string => sprintf(
                'expire after %s, %d-char codes',
                TeamsConfig::inviteExpiresAfter()->forHumans(),
                TeamsConfig::inviteCodeLength(),
            )),
            'Members' => self::orInvalid(static fn (): string => sprintf(
                'prune %s after expiry, %d-day expiry warning, start dates %s',
                TeamsConfig::membersPruneAfter()->forHumans(),
                TeamsConfig::membersExpiringWithin(),
                TeamsConfig::memberStartDates() ? 'ON' : 'OFF',
            )),
            'Join requests' => self::orInvalid(static fn (): string => 'prune '.TeamsConfig::joinRequestsPruneAfter()->forHumans().' after resolution'),
            'Approvals' => $this->approvals(),
            'Gate' => $this->gate(),
            'Notification queue' => TeamsConfig::isUnset('teams.notifications.queue_connection') ? 'DEFAULT' : 'SET',
            'Team context' => self::orInvalid(static fn (): string => sprintf(
                'route parameter %s, non-members get %d, queued jobs re-check membership %s',
                TeamsConfig::contextRouteParameter() !== 'team' ? 'SET' : 'DEFAULT',
                TeamsConfig::contextDenyStatus(),
                TeamsConfig::recheckQueuedJobs() ? 'ON' : 'OFF',
            )),
            'Team scoping' => self::orInvalid(static fn (): string => TeamsConfig::throwWithoutContext()
                ? 'throw without a context'
                : 'no rows without a context'),
            'Nested teams' => $this->nesting(),
            'Permissions bridge' => self::orInvalid(static fn (): string => TeamsConfig::permissionsBridge() ? 'ON' : 'OFF'),
        ];
    }

    private function countOf(int $count, string $noun): string
    {
        return $count === 0 ? 'NONE' : $count.' '.Str::plural($noun, $count);
    }

    /**
     * Presence, never the keys: a host renames these to its own vocabulary.
     */
    private function roleKeys(): string
    {
        return self::orInvalid(static function (): string {
            $packaged = TeamsConfig::ownerRole() === 'owner'
                && TeamsConfig::adminRole() === 'admin'
                && TeamsConfig::defaultRole() === 'member';

            return $packaged ? 'DEFAULT' : 'CUSTOMISED';
        });
    }

    private function roleCache(): string
    {
        if (! Config::boolean('teams.roles.cache.enabled')) {
            return 'OFF';
        }

        return self::orInvalid(static fn (): string => sprintf(
            'ON (store %s, ttl %ds)',
            TeamsConfig::cacheStore() !== null ? 'SET' : 'DEFAULT',
            TeamsConfig::cacheTtl(),
        ));
    }

    private function approvals(): string
    {
        if (! Config::boolean('teams.approvals.enabled')) {
            return 'OFF';
        }

        return self::orInvalid(static fn (): string => sprintf(
            'ON (rule %s, quorum %s)',
            Config::enum('teams.approvals.rule', ApprovalRule::class, ApprovalRule::Unanimous)->value,
            TeamsConfig::approvalQuorum() ?? 'NONE',
        ));
    }

    private function gate(): string
    {
        if (! Config::boolean('teams.gate.register', true)) {
            return 'OFF';
        }

        return self::orInvalid(static fn (): string => sprintf(
            'ON (prefix %s, owner ability %s)',
            TeamsConfig::gatePrefix() !== 'teams' ? 'SET' : 'DEFAULT',
            TeamsConfig::gateOwnerAbility() !== 'owner' ? 'SET' : 'DEFAULT',
        ));
    }

    private function nesting(): string
    {
        return self::orInvalid(static function (): string {
            if (! TeamsConfig::nestingEnabled()) {
                return 'OFF';
            }

            return sprintf(
                'ON (max depth %d, roles inherit down %s)',
                TeamsConfig::nestingMaxDepth(),
                TeamsConfig::inheritRolesDown() ? 'ON' : 'OFF',
            );
        });
    }

    /**
     * The value a strict read produces, or INVALID when the host's config is malformed:
     * `php artisan about` keeps rendering on a broken host, while the real read path throws.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }
}
