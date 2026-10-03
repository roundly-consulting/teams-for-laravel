<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Teams\Actions\DispatchExpiringMembershipsAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;
use RoundlyConsulting\Teams\Support\TeamsConfig;
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

it('takes the unanimous rule when no default rule is set', function (?string $rule): void {
    config()->set('teams.approvals.enabled', true);
    config()->set('teams.approvals.rule', $rule);

    $team = Team::factory()->create();

    $request = Teams::for($team)->joinRequests()->requireApprovalFrom(User::create())->open(User::create());

    expect($request->approvalRequests()->first()?->rule)->toBe(ApprovalRule::Unanimous);
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('refuses to migrate on an unrecognized key type (strict config)', function (): void {
    config()->set('teams.key_type', 'nonsense');

    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/0001_create_teams_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});

dataset('teams env integers', [
    'role cache ttl' => ['TEAMS_ROLES_CACHE_TTL', 'roles.cache.ttl', 3600],
    'invite code length' => ['TEAMS_INVITES_CODE_LENGTH', 'invites.code_length', 32],
    'expiring window' => ['TEAMS_MEMBERS_EXPIRING_WITHIN', 'members.expiring_within', 7],
    'approval quorum' => ['TEAMS_APPROVALS_QUORUM', 'approvals.quorum', null],
]);

it('hands a mistyped env integer through raw (strict config)', function (string $name, string $path): void {
    expect(data_get(teamsConfigWithEnv($name, 'abc'), $path))->toBe('abc')
        ->and(data_get(teamsConfigWithEnv($name, '1.5'), $path))->toBe('1.5');
})->with('teams env integers');

it('keeps the integer defaults when the env is unset (strict config)', function (string $name, string $path, ?int $default): void {
    expect(data_get(teamsConfigWithEnv('TEAMS_UNRELATED', 'x'), $path))->toBe($default);
})->with('teams env integers');

it('refuses a mistyped role provider instead of using the array one (strict config)', function (mixed $provider): void {
    config()->set('teams.roles.provider', $provider);
    app()->forgetInstance(RoleProvider::class);

    expect(fn () => app(RoleProvider::class))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [teams.roles.provider] must be one of [array, database]');
})->with(['typo' => 'databse', 'wrong case' => 'Database', 'integer' => 1]);

it('resolves the array role provider when none is set (strict config)', function (?string $provider): void {
    config()->set('teams.roles.provider', $provider);
    app()->forgetInstance(RoleProvider::class);

    expect(app(RoleProvider::class))->toBeInstanceOf(InMemoryRoleProvider::class);
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('refuses a junk or out-of-range invite code length (strict config)', function (mixed $length): void {
    config()->set('teams.invites.code_length', $length);
    $team = Team::factory()->create();

    expect(fn () => Teams::for($team)->invites()->create('member'))
        ->toThrow(InvalidConfigurationException::class, 'teams.invites.code_length')
        ->and($team->invites()->count())->toBe(0);
})->with(['word' => 'abc', 'decimal' => '5.5', 'zero' => 0, 'below the minimum' => 7, 'above the maximum' => 129, 'over the column' => 256]);

it('accepts an invite code length at either end of 8-128 (strict config)', function (int|string $length, int $expected): void {
    config()->set('teams.invites.code_length', $length);

    expect(Teams::for(Team::factory()->create())->invites()->create('member')->code)->toHaveLength($expected);
})->with(['minimum' => [8, 8], 'maximum' => [128, 128], 'env string' => ['128', 128]]);

it('refuses a junk code length on resend too (strict config)', function (): void {
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create('member');
    config()->set('teams.invites.code_length', 'abc');

    expect(fn () => Teams::for($team)->invites()->resend($invite))
        ->toThrow(InvalidConfigurationException::class, 'teams.invites.code_length');
});

it('generates a 32-character code when the length is not set (strict config)', function (?string $length): void {
    config()->set('teams.invites.code_length', $length);

    expect(Teams::for(Team::factory()->create())->invites()->create('member')->code)->toHaveLength(32);
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('refuses a junk or non-positive invite expiry (strict config)', function (mixed $interval): void {
    config()->set('teams.invites.expires_after', $interval);
    $team = Team::factory()->create();

    expect(fn () => Teams::for($team)->invites()->create('member'))
        ->toThrow(InvalidConfigurationException::class, 'teams.invites.expires_after');
})->with(['word' => 'seven days', 'typo' => '7 dayz', 'bare number' => '30', 'zero' => '0 days', 'negative' => '-7 days', 'integer' => 7]);

it('expires an invite after 7 days when the interval is not set (strict config)', function (?string $interval): void {
    config()->set('teams.invites.expires_after', $interval);
    $this->freezeSecond();

    expect(Teams::for(Team::factory()->create())->invites()->create('member')->expires_at?->toDateTimeString())
        ->toBe(now()->addDays(7)->toDateTimeString());
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('refuses a junk or negative prune interval (strict config)', function (string $key, Closure $prunable, mixed $interval): void {
    config()->set($key, $interval);

    expect($prunable)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'members' => ['teams.members.prune_after', fn () => (new Member)->prunable()],
    'join requests' => ['teams.join_requests.prune_after', fn () => (new JoinRequest)->prunable()],
])->with(['word' => 'thirty days', 'negative' => '-30 days', 'integer' => 30]);

it('prunes 30 days on when the prune interval is not set (strict config)', function (string $key, Closure $window, ?string $interval): void {
    config()->set($key, $interval);

    expect($window()->totalDays)->toEqual(30);
})->with([
    'members' => ['teams.members.prune_after', fn () => TeamsConfig::membersPruneAfter()],
    'join requests' => ['teams.join_requests.prune_after', fn () => TeamsConfig::joinRequestsPruneAfter()],
])->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('accepts a zero prune interval (strict config)', function (): void {
    config()->set('teams.members.prune_after', '0 days');

    expect((new Member)->prunable()->count())->toBe(0);
});

it('refuses a junk or non-positive expiring window (strict config)', function (mixed $days): void {
    config()->set('teams.members.expiring_within', $days);

    expect(fn () => app(DispatchExpiringMembershipsAction::class)->execute())
        ->toThrow(InvalidConfigurationException::class, 'teams.members.expiring_within')
        ->and(fn () => Artisan::call('teams:members:expiring'))
        ->toThrow(InvalidConfigurationException::class, 'teams.members.expiring_within');
})->with(['word' => 'five', 'decimal' => '7.5', 'zero' => 0, 'negative' => '-1']);

it('takes the 7-day expiring window when none is set (strict config)', function (?string $days): void {
    config()->set('teams.members.expiring_within', $days);

    expect(TeamsConfig::membersExpiringWithin())->toBe(7);
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('refuses a junk or non-positive default quorum (strict config)', function (mixed $quorum): void {
    config()->set('teams.approvals.enabled', true);
    config()->set('teams.approvals.quorum', $quorum);

    $team = Team::factory()->create();

    expect(fn () => Teams::for($team)->joinRequests()->requireApprovalFrom(User::create())->open(User::create()))
        ->toThrow(InvalidConfigurationException::class, 'teams.approvals.quorum');
})->with(['word' => 'two', 'zero' => 0, 'decimal' => '1.5']);

it('sets no default quorum when none is set (strict config)', function (?string $quorum): void {
    config()->set('teams.approvals.enabled', true);
    config()->set('teams.approvals.quorum', $quorum);

    $team = Team::factory()->create();
    $request = Teams::for($team)->joinRequests()->requireApprovalFrom(collect([User::create(), User::create()]))->open(User::create());

    expect(TeamsConfig::approvalQuorum())->toBeNull()
        ->and($request->approvalRequests()->first()?->rule)->toBe(ApprovalRule::Unanimous)
        ->and($request->approvalRequests()->first()?->required_approvers)->toBe(2);
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('refuses a junk role cache ttl, store or key (strict config)', function (string $key, mixed $value): void {
    config()->set('teams.roles.provider', 'database');
    config()->set('teams.roles.cache.enabled', true);
    config()->set($key, $value);

    $provider = new CachedRoleProvider(new DatabaseRoleProvider(memoize: false));

    expect(fn () => $provider->all())->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'ttl word' => ['teams.roles.cache.ttl', 'an hour'],
    'ttl zero' => ['teams.roles.cache.ttl', 0],
    'array store' => ['teams.roles.cache.store', ['redis']],
    'array key' => ['teams.roles.cache.key', ['teams.roles']],
]);

it('takes the default role cache store, key and ttl when they are not set (strict config)', function (?string $blank): void {
    config()->set('teams.roles.cache.store', $blank);
    config()->set('teams.roles.cache.key', $blank);
    config()->set('teams.roles.cache.ttl', $blank);

    expect(TeamsConfig::cacheStore())->toBeNull()
        ->and(TeamsConfig::cacheKey())->toBe('teams.roles')
        ->and(TeamsConfig::cacheTtl())->toBe(3600);
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('refuses a non-string role key (strict config)', function (string $key, mixed $value, Closure $act): void {
    config()->set($key, $value);

    expect($act)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'owner role' => ['teams.roles.owner', ['owner'], fn () => Teams::create(new CreateTeamData('Acme', owner: User::create()))],
    'admin role' => ['teams.roles.admin', 42, fn () => Teams::for(Teams::create(new CreateTeamData('Acme', owner: User::create())))->transferOwnershipTo(User::create())],
    'default role' => ['teams.roles.default', ['member'], fn () => Teams::for(Team::factory()->create())->joinRequests()->open(User::create())],
]);

it('takes the default role keys when they are not set (strict config)', function (?string $blank): void {
    config()->set('teams.roles.owner', $blank);
    config()->set('teams.roles.admin', $blank);
    config()->set('teams.roles.default', $blank);

    $owner = User::create();
    $team = Teams::create(new CreateTeamData('Acme', owner: $owner));

    expect([TeamsConfig::ownerRole(), TeamsConfig::adminRole(), TeamsConfig::defaultRole()])->toBe(['owner', 'admin', 'member'])
        ->and($team->members()->firstOrFail()->role)->toBe('owner');
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('refuses a non-string gate prefix or owner ability (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    $provider = new TeamsServiceProvider(app());

    expect(fn () => (new ReflectionMethod($provider, 'registerGate'))->invoke($provider))
        ->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'array prefix' => ['teams.gate.prefix', ['teams']],
    'integer owner ability' => ['teams.gate.owner_ability', 42],
]);

it('takes the default gate prefix and owner ability when they are not set (strict config)', function (?string $blank): void {
    config()->set('teams.gate.prefix', $blank);
    config()->set('teams.gate.owner_ability', $blank);

    expect(TeamsConfig::gatePrefix())->toBe('teams')
        ->and(TeamsConfig::gateOwnerAbility())->toBe('owner');
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('keeps the about section rendering on a malformed host config (strict config)', function (): void {
    config()->set('teams.roles.provider', 'databse');
    config()->set('teams.invites.code_length', 'abc');
    config()->set('teams.members.expiring_within', 'five');
    config()->set('teams.join_requests.prune_after', 'thirty days');
    config()->set('teams.approvals.enabled', true);
    config()->set('teams.approvals.quorum', 'two');
    app()->forgetInstance(RoleProvider::class);
    app()->instance(RoleProvider::class, new InMemoryRoleProvider);

    expect('teams')->toLeakNoSecrets(
        secrets: ['databse', 'thirty days'],
        mustRender: ['Role provider', 'Invites', 'Members', 'Join requests', 'Approvals', 'INVALID'],
    );
});

it('reports the default notification queue when none is set (strict config)', function (?string $connection): void {
    config()->set('teams.notifications.queue_connection', $connection);

    Artisan::call('about', ['--only' => 'teams', '--json' => true]);

    /** @var array{teams: array<string, string>} $about */
    $about = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($about['teams']['notification_queue'])->toBe('DEFAULT');
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);
