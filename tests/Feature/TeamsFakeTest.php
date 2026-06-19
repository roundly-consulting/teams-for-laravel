<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Testing\TeamsFake;
use RoundlyConsulting\Teams\Tests\User;

it('swaps the binding and still performs operations', function (): void {
    $fake = Teams::fake();

    expect($fake)->toBeInstanceOf(TeamsFake::class)
        ->and(app('teams'))->toBe($fake);

    $team = Teams::createTeam(new CreateTeamData(name: 'Acme'));

    expect(Team::query()->whereKey($team->getKey())->exists())->toBeTrue();
});

it('records and asserts created teams', function (): void {
    Teams::fake();

    Teams::createTeam(new CreateTeamData(name: 'Acme'));

    Teams::assertTeamCreated();
    Teams::assertTeamCreated('Acme');
});

it('fails the team-created assertion when nothing was created', function (): void {
    $fake = Teams::fake();

    expect(fn () => $fake->assertTeamCreated())->toThrow(AssertionFailedError::class);
    $fake->assertNothingCreated();
});

it('asserts membership and invites', function (): void {
    Teams::fake();

    $team = Team::factory()->create();
    $user = User::create();

    Teams::for($team)->addMember($user, 'admin');
    Teams::for($team)->invite('admin');

    Teams::assertMemberAdded($team, $user);
    Teams::assertInviteCreated($team);
    Teams::assertMemberNotAdded($team, User::create());
});
