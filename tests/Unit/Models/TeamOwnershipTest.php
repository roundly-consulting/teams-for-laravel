<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('has an owner morph relationship', function () {
    expect((new Team)->owner())->toBeInstanceOf(MorphTo::class);
});

it('reports ownership correctly', function () {
    $owner = User::create();
    $other = User::create();

    $team = Team::factory()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->id,
    ]);

    expect($team)
        ->isOwnedBy($owner)->toBeTrue()
        ->isOwnedBy($other)->toBeFalse();
});

it('returns false ownership for a team with no owner', function () {
    $team = Team::factory()->create();
    $user = User::create();

    expect($team->isOwnedBy($user))->toBeFalse();
});
