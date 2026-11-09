<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Bridges\Permissions\PermissionsBridge;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

/**
 * permissions-for-laravel is a `suggest` (◇): teams must install and run without it.
 */
it('suggests permissions, dev-requires it for its own suite and refuses a release too old for the bridge', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require'])->not->toHaveKey('roundly-consulting/permissions-for-laravel')
        ->and($composer['require-dev']['roundly-consulting/permissions-for-laravel'] ?? null)->toBe('^2.1')
        ->and($composer['suggest'])->toHaveKey('roundly-consulting/permissions-for-laravel')
        ->and($composer['conflict']['roundly-consulting/permissions-for-laravel'] ?? null)->toBe('<2.1');
});

it('is off by default', function (): void {
    expect(config('teams.permissions.bridge'))->toBeFalse()
        ->and(PermissionsBridge::enabled())->toBeFalse();
})->skip(fn (): bool => in_array(strtolower((string) getenv('TEAMS_TEST_PERMISSIONS_BRIDGE')), ['1', 'on', 'true', 'yes'], true), 'the bridge-on run');

it('refuses member grants while off', function (): void {
    config()->set('teams.permissions.bridge', false);
    $team = Team::factory()->create();

    expect(fn () => Teams::for($team)->members()->grant(User::create()))
        ->toThrow(TeamsException::class, 'Member grants need the permissions bridge');
});
