<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Teams\Roles\Roles;
use RoundlyConsulting\Teams\TeamsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'RoundlyConsulting\\Teams\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Roles::register('admin', 'Admin', ['*']);
        Roles::register('user', 'User');
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            TeamsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable();
        });
    }
}
