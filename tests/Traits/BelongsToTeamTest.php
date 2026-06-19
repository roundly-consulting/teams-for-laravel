<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('has relationship to team', function () {
    $user = new User;

    expect($user->team())->toBeInstanceOf(BelongsTo::class);
});

it('returns current user team', function () {
    $team = Team::factory()->create();
    $user = User::create(['team_id' => $team->id]);

    expect($user->team)
        ->is($team)->toBeTrue()
        ->id->toBe($team->id);
});

it('switches team', function () {
    $team = Team::factory()->create();
    $newTeam = Team::factory()->create();
    $user = User::create(['team_id' => $team->id]);

    expect($user->team)
        ->is($team)->toBeTrue()
        ->id->toBe($team->id);

    $result = $user->switchTeamTo($newTeam);

    expect($result)
        ->toBeInstanceOf(User::class)
        ->is($user)->toBeTrue()
        ->and($user->team)
        ->is($newTeam)->toBeTrue()->id->toBe($newTeam->id);
});
