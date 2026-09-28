<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\OptionsServiceProvider;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\TeamsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'RoundlyConsulting\\Teams\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Teams::roles()->register('admin', 'Admin', ['*']);
        Teams::roles()->register('user', 'User');

        // The options package memoises resolved values in a static, per-process cache that
        // would otherwise leak across tests.
        Options::flushCache();
    }

    /**
     * Every provider the suite really needs, in registration order — all five providers
     * below are hard `require`s a host would auto-discover, and the team integrations
     * genuinely run on them.
     *
     * `enums-for-laravel` and `package-toolkit-for-laravel` are hard `require`s too, but the
     * first ships no provider and the second is a base class rather than a registered
     * package.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
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

    /**
     * The migrations, named by **provider class** — never by filename.
     *
     * This replaces a hand-rolled `loadProviderSchema()` that reflected on each provider to
     * find its package root and then guessed `/database/migrations` beneath it: exactly what
     * the base case's `LoadsProviderMigrations` concern does once, correctly, for the whole
     * fleet. Order still matters and is preserved — providers before this package, which
     * constrains onto nothing of theirs but reads through them.
     *
     * The `users` fixture table was a bare `Schema::create()` here, i.e. outside the migrator
     * and outside every reset. It is a fixture migration now, so the base case's
     * drop-and-remigrate reset owns it like any other table.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            OptionsServiceProvider::class,
            ContactsServiceProvider::class,
            AddressesServiceProvider::class,
            ConnectionsServiceProvider::class,
            ApprovalsServiceProvider::class,
            TeamsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * Set here rather than in `defineEnvironment()`: the base case does its whole job there
     * (DriverMatrix::configure + these keys + the model swaps), so an override without
     * `parent::` decapitates it silently — no error, no red, DriverMatrix simply never
     * configured and the pgsql leg quietly running sqlite.
     *
     * The connection block this file used to hand-write is gone. It hard-coded sqlite
     * `:memory:` — which is precisely what made a real-engine leg impossible — and set
     * `foreign_key_constraints`, which the base case now sets for every suite. Teams was
     * one of the few packages that already had the pragma right; nothing about FK
     * enforcement changes here.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            // Keep the provider caches out of the way so each test reads fresh state.
            'options.cache.enabled' => false,
            'connections.cache.enabled' => false,
        ];
    }
}
