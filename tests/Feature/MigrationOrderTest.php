<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\TeamsServiceProvider;
use RoundlyConsulting\Teams\Tests\User;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * M + P + R for the six team tables.
 *
 * This file replaces ~150 lines of hand-rolled reinvention: the suite published its own
 * migrations into a temp directory, built its own throwaway database, ran `migrate` against
 * it and read the foreign keys back with `Schema::getForeignKeys()`.
 *
 * The ideas were right, and its own docblock is the reason this row matters: it said in as
 * many words that the checks were "the *committed* pin, not the proof", because SQLite
 * happily creates a table referencing a missing parent and only complains at insert time —
 * so the real proof had been done **by hand, once, against a PostgreSQL server**, and never
 * again. That is precisely the gap the `R` assertion plus the `test-pgsql` leg close: the
 * proof now runs on every CI run rather than living in a comment.
 *
 * The bug it records is this package's own: the shipped directory order was plain
 * alphabetical, so `create_teams_table` sorted LAST and a fresh install died on the very
 * first file with `relation "teams" does not exist`.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * M — the structural, engine-independent order pin.
 *
 * Publish order IS run order (directory sort), so a migration that constrains onto a table an
 * earlier one has not created yet is uninstallable in a host. Five packages shipped exactly
 * that under green SQLite suites; this was one of them.
 *
 * `foreignKeys: 5` pins the edge count: `team_invites`, `team_members`,
 * `team_join_requests` and `team_role_overrides` each constrain `team_id` onto `teams`, and
 * `team_members` additionally constrains `accepted_invite_id` onto `team_invites`.
 *
 * No `tableResolvers` are needed and that is a deliberate observation, not an omission: every
 * edge here is a **bare** `->constrained()`, whose parent Laravel derives from the column
 * name (`team_id` → `teams`), or a literal (`->constrained('team_invites')`). The assertion
 * resolves both without a map — and it never guesses on a non-literal it cannot resolve, so
 * if a future edge constrains through a `Model::table()` seam this pin FAILS loudly rather
 * than silently dropping the edge and leaving `5` passing over a smaller graph.
 *
 * The `member` and `requester` columns are deliberately unconstrained morphs — a member can
 * live in any host table.
 */
it('has a runnable migration order', function () use ($migrations): void {
    expect($migrations)->toHaveRunnableMigrationOrder(foreignKeys: 5);
});

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `count: 6` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(TeamsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes every migration timestamp-injected into the host', function (): void {
    expect(TeamsServiceProvider::class)->toPublishMigrationsTimestamped('teams-migrations', 6);
});

/**
 * R — the real-engine proof, and the whole point of this row. The deleted local version ran
 * the published files against a throwaway SQLite database — the one engine that cannot fail
 * this class of check — and openly said so. `migrations: 6` pins the count, and the
 * expectation additionally fails a set that "applies cleanly" while creating no tables (an
 * empty `up()` otherwise passes and proves nothing).
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 6);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The negative control — the "negative control (invites before teams) was watched being
 * rejected" from the deleted file's docblock, turned into a test that runs. A green FK check
 * proves nothing until you have watched the engine actually reject a broken order (forms
 * #28). This fails loudly if the engine ACCEPTS the reordered set, which is what makes the
 * positive half above meaningful.
 */
it('rejects a child-before-parent order on postgres', function () use ($migrations): void {
    expect($migrations)->toRejectBrokenOrderOnConnection(
        fn (array $files): array => array_reverse($files),
        'pgsql',
    );
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin: compares the env-declared driver against what the connection itself
 * answers, so a leg that exports the location vars but not `TESTING_DB_DRIVER` (or a TestCase
 * that decapitates the base case by overriding `defineEnvironment()` without `parent::`) reds
 * instead of quietly running sqlite and reporting green as a "postgres" job. Strictly stronger
 * than reading a skip count by hand.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The jsonb columns and the morph columns are what the drivers render differently — `json`
 * has no equality operator on Postgres at all. Pinning a round-trip on whatever engine the leg
 * configured proves the columns are usable rather than merely creatable, and that the five
 * foreign keys are satisfiable rather than merely present.
 */
it('round-trips a team and its membership on the configured engine', function (): void {
    $owner = User::query()->create();
    $member = User::query()->create();

    $team = Teams::createTeam(new CreateTeamData(
        name: 'Acme',
        owner: $owner,
        meta: ['tier' => 2, 'region' => 'eu'],
    ));

    $membership = $team->addMember($member, 'admin');

    $fresh = $team->fresh();

    expect($fresh?->name)->toBe('Acme')
        ->and($team->hasMember($member))->toBeTrue()
        ->and($membership->role)->toBe('admin')
        // The morph columns really resolve on the configured engine, not just on sqlite's
        // type affinity.
        ->and($membership->member_type)->toBe($member->getMorphClass())
        ->and($membership->member_id)->toBe($member->getKey())
        // Key-by-key rather than `toBe` on the whole map: jsonb sorts object keys (by
        // length, then bytewise), so `['tier' => 2, 'region' => 'eu']` comes back reordered
        // and a whole-map `toBe` (`===`, order-sensitive) would red on Postgres while
        // passing on sqlite. `toEqual` would hide the opposite bug — it is `==`, so it would
        // accept the string "2" for the int 2, which is what a round-trip pin exists to catch.
        ->and($fresh?->meta['tier'] ?? null)->toBe(2)
        ->and($fresh?->meta['region'] ?? null)->toBe('eu');
});
