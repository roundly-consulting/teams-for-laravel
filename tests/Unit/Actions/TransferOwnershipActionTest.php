<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\TransferOwnershipAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Events\TeamOwnershipTransferred;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Tests\User;

it('transfers ownership and demotes the previous owner to admin', function () {
    Event::fake(TeamOwnershipTransferred::class);
    config()->set('teams.roles.owner', 'owner');
    config()->set('teams.roles.admin', 'admin');

    $oldOwner = User::create();
    $newOwner = User::create();

    $team = app(TeamsManager::class)->create(new CreateTeamData(name: 'Acme', owner: $oldOwner));

    app(TransferOwnershipAction::class)->execute($team, $newOwner);

    expect($team->fresh()->isOwnedBy($newOwner))->toBeTrue()
        ->and($team->memberHasRole($newOwner, 'owner'))->toBeTrue()
        ->and($team->memberHasRole($oldOwner, 'admin'))->toBeTrue();

    Event::assertDispatched(fn (TeamOwnershipTransferred $e) => $e->team->is($team)
        && $e->previousOwner?->is($oldOwner)
        && $e->newOwner->is($newOwner));
});

it('transfers ownership when there is no previous owner', function () {
    Event::fake(TeamOwnershipTransferred::class);

    $team = Team::factory()->create();
    $newOwner = User::create();

    app(TransferOwnershipAction::class)->execute($team, $newOwner);

    expect($team->fresh()->isOwnedBy($newOwner))->toBeTrue()
        ->and($team->memberHasRole($newOwner, 'owner'))->toBeTrue();

    Event::assertDispatched(fn (TeamOwnershipTransferred $e) => $e->previousOwner === null);
});

it('changes nothing when the new owner cannot be seated', function () {
    Teams::roles()->register('owner', 'Owner', ['*']);
    $owner = User::create();
    $team = Teams::create(new CreateTeamData(name: 'Acme', owner: $owner));
    Teams::for($team)->settings()->setMaxSeats(1);
    $newcomer = User::create();

    expect(fn () => Teams::for($team)->transferOwnershipTo($newcomer))
        ->toThrow(TeamsException::class, 'seat limit');

    $team->refresh();

    expect($team->isOwnedBy($owner))->toBeTrue()
        ->and($team->isOwnedBy($newcomer))->toBeFalse()
        ->and($team->hasMember($newcomer))->toBeFalse()
        ->and($team->findMember($owner)?->role)->toBe('owner');
});
