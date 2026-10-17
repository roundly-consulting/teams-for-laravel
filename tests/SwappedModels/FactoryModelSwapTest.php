<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomInvite;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomJoinRequest;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomMember;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeam;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeamRole;

/*
 * A factory names its packaged model in `$model`, so without a `modelName()` override that
 * returns the seam it keeps building the packaged class after a host swapped it — and the
 * host's events, observers and casts never run on factory-made rows.
 */
it('builds the configured team from the factory', function (): void {
    expect(Team::factory()->create()::class)->toBe(CustomTeam::class);
});

it('builds the configured member and its team from the factory', function (): void {
    $member = Member::factory()->create();

    expect($member::class)->toBe(CustomMember::class)
        ->and($member->team()->firstOrFail()::class)->toBe(CustomTeam::class);
});

it('builds the configured invite from the factory', function (): void {
    expect(Invite::factory()->create()::class)->toBe(CustomInvite::class);
});

it('builds the configured team role from the factory', function (): void {
    expect(TeamRole::factory()->create()::class)->toBe(CustomTeamRole::class);
});

it('builds the configured join request from the factory', function (): void {
    expect(JoinRequest::factory()->create()::class)->toBe(CustomJoinRequest::class);
});
