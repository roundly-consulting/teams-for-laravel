<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Illuminate\Support\ServiceProvider;

final class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/teams.php', 'teams');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/teams.php' => config_path('teams.php'),
            ], 'teams-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'teams-migrations');
        }
    }
}
