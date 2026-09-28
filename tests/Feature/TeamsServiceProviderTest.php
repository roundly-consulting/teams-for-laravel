<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\TeamsServiceProvider;

/**
 * Migrations are PUBLISH-ONLY: the provider registers a publish group and loads
 * nothing, so a bare `php artisan migrate` in a host does not create the package's
 * tables. This pins the policy itself against regression.
 */
it('never auto-loads its migrations', function (): void {
    $packageMigrations = realpath(__DIR__.'/../../database/migrations');

    $loaded = array_map(
        static fn (string $path): string => (string) realpath($path),
        app('migrator')->paths(),
    );

    expect($loaded)->not->toContain($packageMigrations);
});

it('publishes every migration timestamp-injected under the teams-migrations tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(TeamsServiceProvider::class, 'teams-migrations');

    expect($paths)->toHaveCount(6);

    $destinations = array_values(array_map(
        static fn (string $target): string => basename($target),
        $paths,
    ));

    sort($destinations);

    foreach ($destinations as $destination) {
        expect($destination)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_000\d_create_\w+_table\.php$/');
    }

    // The published timestamps preserve the dependency order — teams first, and
    // team_invites before team_members, which constrains onto it.
    $order = array_map(
        static fn (string $destination): string => (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $destination),
        $destinations,
    );

    expect($order)->toBe([
        '0001_create_teams_table.php',
        '0002_create_team_roles_table.php',
        '0003_create_team_invites_table.php',
        '0004_create_team_members_table.php',
        '0005_create_team_join_requests_table.php',
        '0006_create_team_role_overrides_table.php',
    ]);
});

it('keeps every publish tag and destination byte-identical', function (): void {
    $config = ServiceProvider::pathsToPublish(TeamsServiceProvider::class, 'teams-config');
    expect(array_values($config))->toBe([config_path('teams.php')]);

    $translations = ServiceProvider::pathsToPublish(TeamsServiceProvider::class, 'teams-translations');
    expect(array_values($translations))->toBe([app()->langPath('vendor/teams')]);

    $stubs = ServiceProvider::pathsToPublish(TeamsServiceProvider::class, 'teams-stubs');
    expect(array_values($stubs))->toBe([
        app_path('Http/Controllers/AcceptInviteController.php'),
        base_path('routes/teams.php'),
        app_path('Listeners/TeamEventSubscriber.php'),
        base_path('tests/Teams.php'),
    ]);
});

it('registers the package translations under the teams namespace', function (): void {
    expect(trans('teams::errors.invite_not_found', ['code' => 'abc']))
        ->not->toBe('teams::errors.invite_not_found');
});

it('registers every console command', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)
        ->toContain('teams:roles')
        ->toContain('teams:permissions')
        ->toContain('teams:invites:prune')
        ->toContain('teams:members:prune')
        ->toContain('teams:join-requests:prune')
        ->toContain('teams:members:expiring')
        ->toContain('teams:invites:resend')
        ->toContain('teams:policy');
});

/**
 * A — the secret-safe `about` capture.
 *
 * A team's role keys, permissions and gate abilities are the HOST's own authorization
 * vocabulary — a role key names a business function and a permission names what it may do.
 * The section reports models, switches, bounds and counts, and must never render one of
 * them, nor a cache store or a queue connection.
 *
 * The local version this replaces was already non-vacuous: it captured through
 * `Artisan::call()` + `Artisan::output()`, not `app(Kernel::class)->output()` — the `''` that
 * made purchases #13's entire leak check pass against empty output — and it even wrote
 * "guard the guard" above its own non-empty check. The preset is adopted because it makes
 * that ordering structural rather than a habit: `mustRender` is required and non-empty, the
 * capture is asserted non-empty, and every positive is proven present BEFORE any secret is
 * looked for. A negative-only case cannot be written with it.
 */
it('reports the package in about without leaking the host vocabulary', function (): void {
    config()->set('teams.roles.default', 'series-c-signatory');
    config()->set('teams.roles.cache.enabled', true);
    config()->set('teams.roles.cache.store', 'tenant-redis');
    config()->set('teams.gate.prefix', 'acme-authz');
    config()->set('teams.gate.owner_ability', 'principal');
    config()->set('teams.notifications.queue_connection', 'sqs-tenant-eu');

    Teams::roles()->register('series-c-signatory', 'Signatory', ['treasury.wire']);
    Teams::permissions()->register('treasury.wire', 'Wire funds');

    expect('teams')->toLeakNoSecrets(
        secrets: [
            // A role key names a host business function; a permission names what it may do.
            // Both are reported by count only.
            'series-c-signatory',
            'treasury.wire',
            // Gate vocabulary — reported as CUSTOMISED/DEFAULT, never by name.
            'acme-authz',
            'principal',
            // Host infrastructure — reported by presence only.
            'tenant-redis',
            'sqs-tenant-eu',
        ],
        mustRender: [
            'Team model',
            'Team',
            // The positive halves that prove the lines report rather than sit empty: the
            // counts themselves and the presence markers. Without these the secret checks
            // above would be aimed at a section that might have printed nothing at all. The
            // suite registers `admin` and `user` on top of the role above, so the count is
            // three.
            'CUSTOMISED',
            'SET',
            '3 roles',
            '1 permission',
        ],
    );
});
