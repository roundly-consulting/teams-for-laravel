<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\TeamsServiceProvider;
use RoundlyConsulting\Teams\Tests\User;

/*
 | A typo in a host's config or env must fail loudly, never quietly become a default. The
 | config file therefore hands every env switch through as the raw string — a `(bool)` cast
 | there read `TEAMS_APPROVALS=false` as ON and `=disabled` as ON — and every switch is read
 | through the toolkit's strict `Config::boolean()`, which refuses what it cannot parse.
 */

/**
 * Evaluate the shipped config file with one env variable set, the way a host boots it.
 *
 * @return array<string, mixed>
 */
function teamsConfigWithEnv(string $name, string $value): array
{
    $_SERVER[$name] = $_ENV[$name] = $value;
    putenv("{$name}={$value}");

    try {
        /** @var array<string, mixed> */
        return require __DIR__.'/../../config/teams.php';
    } finally {
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);
    }
}

dataset('teams env switches', [
    'per-team roles' => ['TEAMS_PER_TEAM_ROLES', 'roles.per_team'],
    'role cache' => ['TEAMS_ROLES_CACHE', 'roles.cache.enabled'],
    'approvals' => ['TEAMS_APPROVALS', 'approvals.enabled'],
    'gate' => ['TEAMS_REGISTER_GATE', 'gate.register'],
]);

it('hands a mistyped env switch through raw (strict config)', function (string $name, string $path): void {
    expect(data_get(teamsConfigWithEnv($name, 'disabled'), $path))->toBe('disabled');
})->with('teams env switches');

it('reads the env switch spellings as booleans', function (string $name, string $path): void {
    foreach (['true' => true, '1' => true, 'on' => true, 'yes' => true, 'false' => false, '0' => false, 'off' => false, 'no' => false] as $value => $expected) {
        expect(Config::for(teamsConfigWithEnv($name, (string) $value))->boolean($path))->toBe($expected);
    }
})->with('teams env switches');

it('keeps the switch defaults as real booleans when the env is unset', function (string $path, bool $default): void {
    expect(data_get(teamsConfigWithEnv('TEAMS_UNRELATED', 'x'), $path))->toBe($default);
})->with([
    'per-team roles' => ['roles.per_team', false],
    'role cache' => ['roles.cache.enabled', false],
    'approvals' => ['approvals.enabled', false],
    'gate' => ['gate.register', true],
]);

it('refuses a mistyped per-team roles switch (strict config)', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    Teams::for($team)->members()->add($user, 'member');

    config()->set('teams.roles.per_team', 'disabled');

    /** @var Member $member */
    $member = $team->members()->firstOrFail();

    expect(fn () => $member->role())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.roles.per_team] must be a boolean');
});

it('refuses a mistyped role cache switch (strict config)', function (): void {
    config()->set('teams.roles.provider', 'database');
    config()->set('teams.roles.cache.enabled', 'disabled');
    app()->forgetInstance(RoleProvider::class);

    expect(fn () => app(RoleProvider::class))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.roles.cache.enabled] must be a boolean');
});

it('refuses a mistyped approvals switch (strict config)', function (): void {
    config()->set('teams.approvals.enabled', 'disabled');

    $team = Team::factory()->create();

    expect(fn () => Teams::for($team)->joinRequests()->requireApprovalFrom(User::create())->open(User::create()))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.approvals.enabled] must be a boolean');
});

it('refuses a mistyped gate switch (strict config)', function (): void {
    config()->set('teams.gate.register', 'disabled');

    $provider = new TeamsServiceProvider(app());

    expect(fn () => (new ReflectionMethod($provider, 'registerGate'))->invoke($provider))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.gate.register] must be a boolean');
});

it('refuses an unknown default approval rule (strict config)', function (): void {
    config()->set('teams.approvals.enabled', true);
    config()->set('teams.approvals.rule', 'unanimus');

    $team = Team::factory()->create();

    expect(fn () => Teams::for($team)->joinRequests()->requireApprovalFrom(User::create())->open(User::create()))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.approvals.rule]');
});

it('takes the unanimous rule when no default rule is configured', function (): void {
    config()->set('teams.approvals.enabled', true);
    config()->set('teams.approvals.rule', null);

    $team = Team::factory()->create();

    $request = Teams::for($team)->joinRequests()->requireApprovalFrom(User::create())->open(User::create());

    expect($request->approvalRequests()->first()?->rule)->toBe(ApprovalRule::Unanimous);
});

it('refuses to migrate on an unrecognized key type (strict config)', function (): void {
    config()->set('teams.key_type', 'nonsense');

    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/0001_create_teams_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});
