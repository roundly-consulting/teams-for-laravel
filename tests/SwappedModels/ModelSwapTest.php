<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomInvite;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomJoinRequest;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomMember;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeam;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeamRole;
use RoundlyConsulting\Teams\Tests\User;

/**
 * S — the model-swap proofs for all five seams, driven through the flows a host calls, from
 * the state a host actually boots in: SwappedModelsTestCase sets every `teams.models.*` key
 * before the providers boot, and this directory is bound to it (Pest binds a test case per
 * directory, not per file).
 *
 * What these add over tests/Feature/ConfiguredModelsTest.php — which is kept, and which is
 * genuinely non-vacuous here because this provider hangs no boot-time wiring on the
 * configured classes — is `CountsCreations`. `instanceof` passes for a row created as the
 * packaged class and re-hydrated, which would fire none of the host's model events
 * (permissions #31); counting `created` events on the exact host class is the only oracle
 * that can tell the two apart.
 *
 * The bug the fixtures exist for is real and this package's own: every `hasMany` on Team has
 * to NAME its foreign key, because Eloquent derives an unnamed one from the PARENT'S CLASS
 * NAME — so a configured `CustomTeam` silently looks for `custom_team_id`, a column no table
 * has. 301 green tests never noticed, because the default path is identical either way.
 */
it('honours a host team model through the creation flow', function (): void {
    expect('teams.models.team')->toHonourModelSwap(CustomTeam::class, function (): array {
        $owner = User::query()->create();

        $team = Teams::createTeam(new CreateTeamData(name: 'Acme', owner: $owner));

        return [
            $team,
            // Reads hydrate through the seam too, not just the writes.
            ...TeamModel::query()->get()->all(),
        ];
    });
});

it('honours a host member model through the membership flow', function (): void {
    expect('teams.models.member')->toHonourModelSwap(CustomMember::class, function (): array {
        $team = Teams::createTeam(new CreateTeamData(name: 'Acme', owner: User::query()->create()));

        $member = $team->addMember(User::query()->create(), 'admin');

        return [
            $member,
            // The relation off the team is where the foreign-key-naming bug lives.
            ...$team->members()->get()->all(),
        ];
    });
});

it('honours a host invite model through the invite flow', function (): void {
    expect('teams.models.invite')->toHonourModelSwap(CustomInvite::class, function (): array {
        $team = Teams::createTeam(new CreateTeamData(name: 'Acme', owner: User::query()->create()));

        $invite = $team->invite(now()->addWeek(), 'user');

        return [
            $invite,
            ...$team->invites()->get()->all(),
        ];
    });
});

it('honours a host team-role model through the per-team role flow', function (): void {
    config()->set('teams.roles.per_team', true);

    expect('teams.models.team_role')->toHonourModelSwap(CustomTeamRole::class, function (): array {
        $team = Teams::createTeam(new CreateTeamData(name: 'Acme', owner: User::query()->create()));

        $role = $team->defineRole('lead', 'Lead', ['posts.publish']);

        return [
            $role,
            ...$team->teamRoles()->get()->all(),
        ];
    });
});

it('honours a host join-request model through the join flow', function (): void {
    expect('teams.models.join_request')->toHonourModelSwap(CustomJoinRequest::class, function (): array {
        $team = Teams::createTeam(new CreateTeamData(name: 'Acme', owner: User::query()->create()));

        $request = app('teams')->requestToJoin($team, User::query()->create(), 'user');

        return [
            $request,
            ...$team->joinRequests()->get()->all(),
        ];
    });
});

// The structural half of all five seams — the models are non-final, and each
// `teams.models.*` key really defaults to the packaged model — is pinned once in
// tests/ArchTest.php by `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does
// NOT live here: that preset asserts the config *defaults*, which this directory has
// swapped away.
