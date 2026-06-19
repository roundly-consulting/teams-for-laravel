<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Actions\CreateTeamAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('creates a team without an owner', function () {
    $team = app(CreateTeamAction::class)->execute(
        new CreateTeamData(name: 'Acme', isPublic: true, meta: ['plan' => 'pro']),
    );

    expect($team)
        ->toBeInstanceOf(Team::class)
        ->name->toBe('Acme')
        ->is_public->toBeTrue()
        ->and($team->meta->toArray())->toBe(['plan' => 'pro'])
        ->and($team->owner_id)->toBeNull();

    expect($team->members()->count())->toBe(0);
});

it('creates a team with an owner and adds them with the owner role', function () {
    config()->set('teams.roles.owner', 'owner');

    $owner = User::create();

    $team = app(CreateTeamAction::class)->execute(
        new CreateTeamData(name: 'Acme', owner: $owner),
    );

    expect($team->isOwnedBy($owner))->toBeTrue();

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'member_id' => $owner->id,
        'member_type' => $owner->getMorphClass(),
        'role' => 'owner',
    ]);
});
