<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Commands\ListRolesCommand;
use RoundlyConsulting\Teams\Commands\PruneInvitesCommand;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;

final class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/teams.php', 'teams');

        $this->app->singleton(RoleProvider::class, function (): RoleProvider {
            return config('teams.roles.provider') === 'database'
                ? new DatabaseRoleProvider
                : new InMemoryRoleProvider;
        });

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
                PruneInvitesCommand::class,
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
        }
    }

    private function registerGate(): void
    {
        if (! config('teams.gate.register')) {
            return;
        }

        /** @var string $prefix */
        $prefix = config('teams.gate.prefix', 'teams');

        Gate::before(function (Model $user, string $ability, array $arguments) use ($prefix): ?bool {
            if (! Str::startsWith($ability, $prefix.'.')) {
                return null;
            }

            $team = $arguments[0] ?? null;

            if (! $team instanceof Team) {
                return null;
            }

            $permission = Str::after($ability, $prefix.'.');

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
    }
}
