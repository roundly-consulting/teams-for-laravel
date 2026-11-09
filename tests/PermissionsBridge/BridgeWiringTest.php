<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Permissions\Facades\Permissions;
use RoundlyConsulting\Teams\Bridges\Permissions\PermissionsBridge;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Permissions\RoleUser;

it('reaches every bridge action through the facade', function (): void {
    expect(Teams::class)->toReachEveryAction(__DIR__.'/../../src/Bridges/Permissions/Actions');
});

it('says why it cannot run', function (): void {
    expect(PermissionsBridge::misconfiguration(installed: false, assignmentsEnabled: false))
        ->toBeInstanceOf(InvalidConfigurationException::class)
        ->getMessage()->toBe('teams.permissions.bridge is on, but roundly-consulting/permissions-for-laravel ^2.1 is not installed.')
        ->and(PermissionsBridge::misconfiguration(installed: true, assignmentsEnabled: false)?->getMessage())
        ->toContain('permissions.assignments.enabled')
        ->and(PermissionsBridge::misconfiguration(installed: true, assignmentsEnabled: true))->toBeNull();
});

it('fails the boot when scoped assignments are off, rather than doing nothing', function (): void {
    config()->set('permissions.assignments.enabled', false);

    expect(fn () => PermissionsBridge::boot())->toThrow(InvalidConfigurationException::class, 'permissions.assignments.enabled');
});

it('says so in about', function (): void {
    Artisan::call('about', ['--only' => 'teams']);

    expect(Artisan::output())->toMatch('/Permissions bridge\s*\.+\s*ON/');
});

it('goes quiet when switched off at runtime', function (): void {
    config()->set('teams.permissions.bridge', false);

    expect(PermissionsBridge::enabled())->toBeFalse()
        ->and(PermissionsBridge::ambientScopes(RoleUser::create()))->toBeNull();
});

it('leaves grants alone when switched off after boot', function (): void {
    Permissions::permission('posts.edit');
    $team = Team::factory()->create();
    $user = RoleUser::create();
    Teams::for($team)->members()->add($user, 'user');
    Permissions::for($user)->in($team)->givePermissionTo('posts.edit');

    config()->set('teams.permissions.bridge', false);
    Teams::for($team)->members()->remove($user);
    $team->forceDelete();

    expect(Permissions::for($user)->in($team)->hasPermissionTo('posts.edit'))->toBeTrue();
});
