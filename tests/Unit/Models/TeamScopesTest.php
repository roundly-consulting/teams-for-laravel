<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('scopes to public teams', function () {
    Team::factory()->create(['is_public' => true]);
    Team::factory()->create(['is_public' => false]);

    expect(Team::query()->public()->count())->toBe(1);
});

it('scopes to teams with a given member', function () {
    $user = User::create();
    $other = User::create();

    $teamWith = Team::factory()->create();
    $teamWith->addMember($user, 'admin');

    Team::factory()->create();

    $results = Team::query()->withMember($user)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($teamWith))->toBeTrue()
        ->and(Team::query()->withMember($other)->count())->toBe(0);
});
