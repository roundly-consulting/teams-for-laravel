<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
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
use RoundlyConsulting\Teams\Commands\ListPermissionsCommand;
use RoundlyConsulting\Teams\Commands\ListRolesCommand;
use RoundlyConsulting\Teams\Commands\MakePolicyCommand;
use RoundlyConsulting\Teams\Commands\MembersExpiringCommand;
use RoundlyConsulting\Teams\Commands\PruneInvitesCommand;
use RoundlyConsulting\Teams\Commands\PruneJoinRequestsCommand;
use RoundlyConsulting\Teams\Commands\PruneMembersCommand;
use RoundlyConsulting\Teams\Commands\ResendInviteCommand;
use RoundlyConsulting\Teams\Listeners\SyncJoinRequestStatusFromApproval;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;
use RoundlyConsulting\Teams\Roles\PermissionRegistry;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;
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
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations' key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        $this->registerGate();

        Event::listen(ApprovalRequestResolved::class, SyncJoinRequestStatusFromApproval::class);
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
                'prune %s after expiry, %d-day expiry warning',
                TeamsConfig::membersPruneAfter()->forHumans(),
                TeamsConfig::membersExpiringWithin(),
            )),
            'Join requests' => self::orInvalid(static fn (): string => 'prune '.TeamsConfig::joinRequestsPruneAfter()->forHumans().' after resolution'),
            'Approvals' => $this->approvals(),
            'Gate' => $this->gate(),
            'Notification queue' => config('teams.notifications.queue_connection') !== null ? 'SET' : 'DEFAULT',
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
