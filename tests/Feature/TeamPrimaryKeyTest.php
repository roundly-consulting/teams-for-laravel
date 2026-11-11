<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamKey;
use RoundlyConsulting\Teams\Support\TeamsSchema;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\UlidTeam;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\UuidTeam;
use RoundlyConsulting\Teams\Tests\User;

/**
 * `teams.primary_key_type`: the teams table's own key and every column pointing at a team.
 * The whole suite also runs with `TEAMS_TEST_TEAM_KEY=ulid` (on Postgres in CI); the tests
 * here pin the setting itself, whatever key the suite migrated.
 */
it('keys the packaged team by teams.primary_key_type', function (string $setting, bool $incrementing, string $keyType, Closure $isKey): void {
    config()->set('teams.primary_key_type', $setting);

    $team = new Team;

    expect($team->getIncrementing())->toBe($incrementing)
        ->and($team->getKeyType())->toBe($keyType)
        ->and(TeamKey::type()->value)->toBe($setting);

    if (! $incrementing) {
        expect($isKey($team->newUniqueId()))->toBeTrue();
    }
})->with([
    'bigint' => ['bigint', true, 'int', fn (): bool => true],
    'uuid' => ['uuid', false, 'string', fn (string $key): bool => Str::isUuid($key)],
    // Lowercase, the form Laravel's own HasUlids writes.
    'ulid' => ['ulid', false, 'string', fn (string $key): bool => Str::isUlid($key) && $key === strtolower($key)],
]);

it('refuses a typo in teams.primary_key_type rather than building bigint keys', function (): void {
    config()->set('teams.primary_key_type', 'uiid');

    expect(fn () => new Team)->toThrow(InvalidConfigurationException::class, 'teams.primary_key_type');
});

it('reads a blank teams.primary_key_type as bigint', function (): void {
    config()->set('teams.primary_key_type', '');

    expect((new Team)->getIncrementing())->toBeTrue()
        ->and(TeamsSchema::keyType())->toBe(KeyType::BigInt);
});

it('keeps a team subclass that keys itself with HasUlids or HasUuids working, and lets it win', function (string $model, KeyType $type, Closure $isKey): void {
    config()->set('teams.primary_key_type', 'bigint');
    config()->set('teams.models.team', $model);

    $team = new $model;

    expect($team->getIncrementing())->toBeFalse()
        ->and($isKey($team->newUniqueId()))->toBeTrue()
        ->and(TeamKey::type())->toBe($type);
})->with([
    'HasUlids' => [UlidTeam::class, KeyType::Ulid, fn (string $key): bool => Str::isUlid($key)],
    'HasUuids' => [UuidTeam::class, KeyType::Uuid, fn (string $key): bool => Str::isUuid($key)],
]);

it('really runs the suite on the key TEAMS_TEST_TEAM_KEY names', function (): void {
    $requested = strtolower(trim((string) getenv('TEAMS_TEST_TEAM_KEY')));
    $type = TeamsSchema::keyType();

    // Not vacuous: the env leg must have switched the setting it claims to switch.
    expect($type->value)->toBe($requested === '' ? 'bigint' : $requested);

    $team = Team::factory()->create();
    $key = $team->getKey();

    match ($type) {
        KeyType::BigInt => expect($key)->toBeInt(),
        KeyType::Uuid => expect(Str::isUuid($key))->toBeTrue(),
        KeyType::Ulid => expect(Str::isUlid($key))->toBeTrue(),
    };

    expect(Team::query()->find($key)?->is($team))->toBeTrue();
});

it('carries a string team key through members, invites, join requests, roles and nesting', function (): void {
    config()->set('teams.roles.per_team', true);
    config()->set('teams.nesting.enabled', true);

    $company = Team::factory()->create();
    $department = Team::factory()->create();
    $user = User::query()->create();

    Teams::for($department)->moveUnder($company);
    Teams::for($department)->members()->add($user, role: 'user');

    $invite = Teams::for($department)->invites()->create(role: 'user', email: 'someone@example.com');
    $invited = Teams::invites()->accept($invite->code, User::query()->create(), 'someone@example.com');

    $request = Teams::for($department)->joinRequests()->open(User::query()->create());
    $approved = Teams::for($department)->joinRequests()->approve($request, $user);

    $role = Teams::for($department)->roles()->define('editor', 'Editor', ['posts.edit']);

    expect($department->refresh()->parent_id)->toEqual($company->getKey())
        ->and(Member::query()->where('team_id', $department->getKey())->count())->toBe(3)
        ->and($invite->team_id)->toEqual($department->getKey())
        ->and($invited->team_id)->toEqual($department->getKey())
        ->and($request->team_id)->toEqual($department->getKey())
        ->and($approved->team_id)->toEqual($department->getKey())
        ->and($role->team_id)->toEqual($department->getKey())
        ->and(Teams::for($department)->roles()->all())->toHaveKey('editor')
        ->and($company->descendants()->pluck('id')->all())->toEqual([$department->getKey()]);
});

it('takes a string team key in DefineTeamRoleData', function (): void {
    $key = strtolower((string) Str::ulid());

    expect((new DefineTeamRoleData($key, 'editor', 'Editor'))->teamId)->toBe($key)
        ->and((new DefineTeamRoleData(7, 'editor', 'Editor'))->teamId)->toBe(7);
});

it('types teams.id and every team column from the setting', function (string $setting, string $idType): void {
    config()->set('teams.primary_key_type', $setting);
    config()->set('database.connections.team_keys', ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);

    $this->artisan('migrate', [
        '--database' => 'team_keys',
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();

    $schema = Schema::connection('team_keys');
    $typeOf = fn (string $table, string $column): string => $schema->getColumnType($table, $column);

    expect($typeOf('teams', 'id'))->toBe($idType)
        ->and($typeOf('teams', 'parent_id'))->toBe($idType)
        ->and($typeOf('team_invites', 'team_id'))->toBe($idType)
        ->and($typeOf('team_members', 'team_id'))->toBe($idType)
        ->and($typeOf('team_join_requests', 'team_id'))->toBe($idType)
        ->and($typeOf('team_role_overrides', 'team_id'))->toBe($idType)
        // Not a team key: it points at team_invites.id, which stays bigint.
        ->and($typeOf('team_members', 'accepted_invite_id'))->toBe('integer')
        ->and(collect($schema->getForeignKeys('team_members'))->firstWhere('columns', ['team_id'])['foreign_table'] ?? null)->toBe('teams');
})->with([
    'bigint' => ['bigint', 'integer'],
    'uuid' => ['uuid', 'varchar'],
    'ulid' => ['ulid', 'varchar'],
]);
