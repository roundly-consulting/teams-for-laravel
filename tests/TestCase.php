<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\OptionsServiceProvider;
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

        // The options package memoises resolved values in a static, per-process
        // cache that would otherwise leak across the fresh in-memory databases.
        Options::flushCache();
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            OptionsServiceProvider::class,
            ContactsServiceProvider::class,
            AddressesServiceProvider::class,
            ApprovalsServiceProvider::class,
            ConnectionsServiceProvider::class,
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
            'foreign_key_constraints' => true,
        ]);

        // Keep the provider caches out of the way so each test reads fresh state.
        $app['config']->set('options.cache.enabled', false);
        $app['config']->set('connections.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadProviderSchema();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable();
        });
    }

    /**
     * Run the migrations of the provider packages the team integrations depend on,
     * each from its own package directory (directory order == dependency order).
     */
    private function loadProviderSchema(): void
    {
        $providers = [
            OptionsServiceProvider::class,
            ContactsServiceProvider::class,
            AddressesServiceProvider::class,
            ConnectionsServiceProvider::class,
            ApprovalsServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $base = dirname((string) (new ReflectionClass($provider))->getFileName(), 2);

            $this->loadMigrationsFrom($base.'/database/migrations');
        }
    }
}
