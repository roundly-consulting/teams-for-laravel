<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;

beforeEach(function (): void {
    config()->set('teams.nesting.enabled', true);
});

it('records team moves', function (): void {
    $fake = Teams::fake();
    $company = Team::factory()->create();
    $team = Team::factory()->create();
    $other = Team::factory()->create();

    $fake->assertNothingMoved();

    Teams::for($team)->moveUnder($company);

    $fake->assertTeamMoved($team);
    $fake->assertTeamMoved($team, under: $company);

    expect(fn () => $fake->assertTeamMoved($team, under: $other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertTeamMoved($other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingMoved())->toThrow(AssertionFailedError::class)
        ->and($team->fresh()->parent_id)->toBe($company->getKey());
});

it('still sets the context under the fake', function (): void {
    Teams::fake();
    $team = Team::factory()->create();

    Teams::setCurrent($team);

    expect(Teams::current()?->is($team))->toBeTrue();
});
