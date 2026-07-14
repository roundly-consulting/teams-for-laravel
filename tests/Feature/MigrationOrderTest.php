<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\TeamsServiceProvider;

/**
 * The package ships six CREATEs wired together by five real foreign keys: four
 * tables constrain onto `teams`, and `team_members` also constrains onto
 * `team_invites`. Publishing preserves the source directory's order, so that order
 * has to be runnable end to end from an empty database.
 *
 * SQLite happily creates a table referencing a missing parent — it only complains at
 * insert time — so these tests are the *committed* pin, not the proof. The order was
 * proved against a real PostgreSQL server, which rejects a dangling foreign key at
 * DDL time: the shipped directory order (plain alphabetical, so `create_teams_table`
 * sorted LAST) died on the very first file with `relation "teams" does not exist`,
 * the fixed order applied all six with all five keys, and a negative control (invites
 * before teams) was watched being rejected.
 *
 * These tests run the *published* files, under their published names, into a database
 * that starts empty — which is exactly what a host does (migrations are publish-only).
 */
beforeEach(function (): void {
    $this->publishedPath = sys_get_temp_dir().'/teams-migration-order-'.bin2hex(random_bytes(6));
    $this->publishedDatabase = $this->publishedPath.'/database.sqlite';

    File::makeDirectory($this->publishedPath, recursive: true);
    File::put($this->publishedDatabase, '');

    foreach (ServiceProvider::pathsToPublish(TeamsServiceProvider::class, 'teams-migrations') as $source => $target) {
        File::copy($source, $this->publishedPath.'/'.basename((string) $target));
    }

    config()->set('database.connections.published', [
        'driver' => 'sqlite',
        'database' => $this->publishedDatabase,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->publishedPath);
});

it('migrates the published files clean from an empty database', function (): void {
    $schema = Schema::connection('published');

    expect($schema->hasTable('teams'))->toBeFalse();

    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    $tables = [
        'teams', 'team_roles', 'team_invites',
        'team_members', 'team_join_requests', 'team_role_overrides',
    ];

    foreach ($tables as $table) {
        expect($schema->hasTable($table))->toBeTrue();
    }
});

it('keeps every foreign key intact in the published schema', function (): void {
    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    $schema = Schema::connection('published');

    $foreignKeys = static fn (string $table): array => array_map(
        static fn (array $key): string => $key['columns'][0].' → '.$key['foreign_table'],
        $schema->getForeignKeys($table),
    );

    // The CREATE order is load-bearing, not incidental: each child really does
    // constrain onto a table created before it.
    expect($foreignKeys('team_invites'))->toContain('team_id → teams')
        ->and($foreignKeys('team_members'))
        ->toContain('team_id → teams')
        ->toContain('accepted_invite_id → team_invites')
        ->and($foreignKeys('team_join_requests'))->toContain('team_id → teams')
        ->and($foreignKeys('team_role_overrides'))->toContain('team_id → teams');
});

/**
 * The structural pin — and the one that would have caught the shipped bug on SQLite.
 *
 * Read every `constrained()` out of the migration sources and assert the parent's
 * CREATE really does sort before the child's. Engine-independent, so it fails in CI
 * (SQLite) the moment someone adds a table whose foreign key outruns its target.
 */
it('creates every foreign key target before the table that references it', function (): void {
    $sources = glob(__DIR__.'/../../database/migrations/*.php');
    sort($sources);

    /** @var array<string, int> $createdAt */
    $createdAt = [];
    /** @var list<array{child: string, parent: string, at: int}> $edges */
    $edges = [];

    foreach ($sources as $position => $source) {
        $body = (string) file_get_contents($source);

        preg_match("/Schema::create\('([a-z_]+)'/", $body, $created);
        expect($created)->not->toBeEmpty();

        $createdAt[$created[1]] = $position;

        // Both forms: `->constrained()` (the parent table is derived from the column
        // name) and `->constrained('explicit_table')`.
        preg_match_all(
            "/foreignId\('([a-z_]+)'\).*?->constrained\(\s*(?:'([a-z_]+)')?\s*\)/s",
            $body,
            $matches,
            PREG_SET_ORDER,
        );

        // Guard the guard: every `->constrained(` in the source was actually paired.
        expect($matches)->toHaveCount(substr_count($body, '->constrained('));

        foreach ($matches as $match) {
            $parent = ($match[2] ?? '') !== ''
                ? $match[2]
                : Str::plural(Str::beforeLast($match[1], '_id'));

            $edges[] = ['child' => $created[1], 'parent' => $parent, 'at' => $position];
        }
    }

    // The package really does emit the foreign keys this test is guarding.
    expect($edges)->toHaveCount(5);

    foreach ($edges as $edge) {
        expect($createdAt)->toHaveKey($edge['parent']);

        expect($createdAt[$edge['parent']])
            ->toBeLessThan(
                $edge['at'],
                "{$edge['child']} references {$edge['parent']}, which must be created first",
            );
    }
});
