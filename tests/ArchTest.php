<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Teams;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Teams shipped a one-line arch file (`dd`/`dump`/`ray`), so every preset here is a new
 * guard rather than a replacement.
 */
ArchPresets::strictTypes('RoundlyConsulting\Teams');

/**
 * The deliberate extension points are exempt — everything else is final.
 *
 * Note the `$ignoring` PARAMETER rather than Pest's fluent `->ignoring()`. Only the parameter
 * is rot-checked (it registers `exemptionsExist` automatically): the fluent form accepts any
 * string and never verifies it, so a typo or an exemption that outlived its code is a silent
 * no-op and the ban then has a hole nobody can see.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Teams', [
    // The five config-swappable models — a host subclasses these, and the preset below
    // pins that they must stay extendable.
    Team::class,
    Member::class,
    Invite::class,
    TeamRole::class,
    JoinRequest::class,
    // The base every teams error extends, so a host can catch them uniformly.
    TeamsException::class,
    // The package extends this itself: Testing\TeamsFake subclasses it to record calls for
    // Teams::fake(). A real, in-tree extension point rather than an oversight.
    Teams::class,
]);

/**
 * The counter-weight to `finalByDefault` above, and the fleet's 7×-shipped fatal: `final` on
 * a config-swappable model is a PHP fatal the moment a host uses the seam the config
 * documents. Teams invites five such swaps and had no rule keeping any of them extendable.
 *
 * It also pins the other direction: that each `teams.models.*` key really defaults to the
 * packaged model, so no seam can rot into naming something else.
 *
 * `teams.roles.provider` is deliberately absent — it is a swappable role *strategy* selected
 * by name (`'array'`/`'database'`), not an Eloquent model behind a model-shaped key, so this
 * preset has nothing to say about it. All five entries here are real Eloquent models, which
 * is why the row is 5 and not a count of config entries that happen to hold a class.
 */
ArchPresets::swappableModelsAreNotFinal([
    Team::class => 'teams.models.team',
    Member::class => 'teams.models.member',
    Invite::class => 'teams.models.invite',
    TeamRole::class => 'teams.models.team_role',
    JoinRequest::class => 'teams.models.join_request',
]);

/**
 * Teams does no cryptography — invite codes are generated with `Str::random()`, which is not
 * a primitive re-implementation. A standing guard against one being hand-rolled here rather
 * than taken from crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Teams');

/**
 * Every `teams.models.*` read goes through the matching Support seam (TeamModel, MemberModel,
 * InviteModel, TeamRoleModel, JoinRequestModel — each delegating to the toolkit's
 * ModelResolver). Adopted rather than rejected as jwt rejected it: teams has exactly the
 * shape the preset targets — real Eloquent models behind model-shaped keys, resolved through
 * a Support seam.
 *
 * The keys are declared rather than left to shape inference. Inference would in fact catch
 * all five here (they carry a `models` segment), but declared keys are unioned with the
 * inferred set and cost nothing, and they make the intent reviewable rather than implicit.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support', [
    'teams.models.team',
    'teams.models.member',
    'teams.models.invite',
    'teams.models.team_role',
    'teams.models.join_request',
]);

/**
 * The morph-key seam, guarded. Teams' `owner` (teams), `member` (team_members),
 * `invited_by` (team_invites) and `requester`/`responded_by` (team_join_requests) columns
 * migrated off raw `$table->morphs()` onto `morphKey($name, KeyType::fromConfig(...))` so a
 * uuid/ulid host can flip its whole graph coherently — a hardcoded bigint id breaks those
 * hosts on Postgres, and SQLite type affinity hides it. This pin reds if a future migration
 * reintroduces a raw morph and bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: teams' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is wrong
 * — never widen the allow-list to quiet it (bug #6 is a true positive).
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

/**
 * Replaces the package's entire previous arch file, which named three functions; the preset
 * covers the full leftover set.
 */
ArchPresets::noDebuggingLeftovers();
