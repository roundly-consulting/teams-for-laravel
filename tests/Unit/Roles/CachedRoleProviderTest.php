<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Teams\Models\RoleDefinition;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;

beforeEach(function (): void {
    config()->set('teams.roles.provider', 'database');
    config()->set('teams.roles.cache.enabled', true);
});

it('caches the role map across calls', function (): void {
    $provider = new CachedRoleProvider(new DatabaseRoleProvider);
    $provider->register('editor', 'Editor', ['posts.edit']);

    // Prime the cache, then mutate the table directly behind the cache's back.
    $first = $provider->all();
    RoleDefinition::query()->where('key', 'editor')->delete();

    expect($provider->all())->toEqual($first)
        ->and($provider->find('editor'))->not->toBeNull();
});

it('flushes the cache on register', function (): void {
    $provider = new CachedRoleProvider(new DatabaseRoleProvider);
    $provider->register('editor', 'Editor', ['posts.edit']);
    $provider->all();

    $provider->register('manager', 'Manager', ['billing.view']);

    expect($provider->all())->toHaveKeys(['editor', 'manager']);
});

it('is wired by the container when cache is enabled', function (): void {
    // The provider singleton is resolved during test setup; force a rebind so
    // the database+cache configuration is honoured.
    app()->forgetInstance(RoleProvider::class);

    expect(app(RoleProvider::class))->toBeInstanceOf(CachedRoleProvider::class);
});

it('works against a tagged store', function (): void {
    config()->set('teams.roles.cache.store', 'array');
    Cache::store('array')->flush();

    $provider = new CachedRoleProvider(new DatabaseRoleProvider);
    $provider->register('editor', 'Editor', ['posts.edit']);
    $provider->all();

    $provider->register('manager', 'Manager', ['billing.view']);

    expect($provider->find('manager'))->not->toBeNull();
});

it('flushes via forget on a non-taggable store', function (): void {
    config()->set('cache.stores.db_cache', ['driver' => 'database', 'table' => 'cache']);
    config()->set('teams.roles.cache.store', 'db_cache');

    Schema::create('cache', function ($table): void {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });

    $provider = new CachedRoleProvider(new DatabaseRoleProvider);
    $provider->register('editor', 'Editor', ['posts.edit']);
    $provider->all();

    $provider->register('manager', 'Manager', ['billing.view']);

    expect($provider->all())->toHaveKeys(['editor', 'manager']);
});
