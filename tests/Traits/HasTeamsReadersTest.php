<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('reports whether the user is on any team', function () {
    $team = Team::factory()->create();
    $member = User::create();
    $loner = User::create();

    $team->addMember($member, 'admin');

    expect($member->hasTeam())->toBeTrue()
        ->and($loner->hasTeam())->toBeFalse();
});

it('aliases membership through isMemberOf', function () {
    $team = Team::factory()->create();
    $member = User::create();
    $team->addMember($member, 'admin');

    expect($member->isMemberOf($team))->toBeTrue();
});

it('returns memberships filtered by role', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $user = User::create();

    $teamA->addMember($user, 'admin');
    $teamB->addMember($user, 'user');

    $admins = $user->teamsWithRole('admin');

    expect($admins)->toHaveCount(1)
        ->and($admins->first()->team_id)->toBe($teamA->id);
});

it('exposes owned teams', function () {
    $owner = User::create();

    $team = Team::factory()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->id,
    ]);

    expect($owner->ownedTeams())->toBeInstanceOf(MorphMany::class)
        ->and($owner->ownedTeams()->get()->pluck('id')->all())->toContain($team->id);
});
