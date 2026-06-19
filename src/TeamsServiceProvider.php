<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Commands\ListPermissionsCommand;
use RoundlyConsulting\Teams\Commands\ListRolesCommand;
use RoundlyConsulting\Teams\Commands\MakePolicyCommand;
use RoundlyConsulting\Teams\Commands\PruneInvitesCommand;
use RoundlyConsulting\Teams\Commands\PruneJoinRequestsCommand;
use RoundlyConsulting\Teams\Commands\PruneMembersCommand;
use RoundlyConsulting\Teams\Commands\ResendInviteCommand;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;
use RoundlyConsulting\Teams\Roles\PermissionRegistry;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;

final class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/teams.php', 'teams');

        $this->app->singleton(RoleProvider::class, function (): RoleProvider {
            if (config('teams.roles.provider') !== 'database') {
                return new InMemoryRoleProvider;
            }

            $provider = new DatabaseRoleProvider;

            return config('teams.roles.cache.enabled')
                ? new CachedRoleProvider($provider)
                : $provider;
        });

        $this->app->singleton(TeamRoleResolver::class);
        $this->app->singleton(PermissionRegistry::class);

        $this->app->singleton('teams', Teams::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'teams');

        $this->registerGate();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListRolesCommand::class,
                ListPermissionsCommand::class,
                PruneInvitesCommand::class,
                PruneMembersCommand::class,
                PruneJoinRequestsCommand::class,
                ResendInviteCommand::class,
                MakePolicyCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/teams.php' => config_path('teams.php'),
            ], 'teams-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'teams-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/teams'),
            ], 'teams-translations');

            $this->publishes([
                __DIR__.'/../stubs/AcceptInviteController.stub' => app_path('Http/Controllers/AcceptInviteController.php'),
                __DIR__.'/../stubs/teams-routes.stub' => base_path('routes/teams.php'),
                __DIR__.'/../stubs/TeamEventSubscriber.stub' => app_path('Listeners/TeamEventSubscriber.php'),
                __DIR__.'/../stubs/Pest.teams.stub' => base_path('tests/Teams.php'),
            ], 'teams-stubs');
        }
    }

    private function registerGate(): void
    {
        if (! config('teams.gate.register')) {
            return;
        }

        /** @var string $prefix */
        $prefix = config('teams.gate.prefix', 'teams');

        /** @var string $ownerAbility */
        $ownerAbility = config('teams.gate.owner_ability', 'owner');

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

            return $team->memberHasPermission($user, $permission) ?: null;
        });

        Blade::if('teamPermission', function (Team $team, string $permission, ?Model $member = null): bool {
            $member ??= auth()->user();

            return $member instanceof Model && $team->memberHasPermission($member, $permission);
        });

        Blade::if('teamRole', function (Team $team, string $role, ?Model $member = null): bool {
            $member ??= auth()->user();

            return $member instanceof Model && $team->memberHasRole($member, $role);
        });

        Blade::if('teamOwner', function (Team $team, ?Model $member = null): bool {
            $member ??= auth()->user();

            return $member instanceof Model && $team->isOwnedBy($member);
        });
    }
}
