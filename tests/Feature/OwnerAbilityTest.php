<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('reports ownership through the HasTeams trait', function (): void {
    $owner = User::create();
    $other = User::create();
    $team = Team::factory()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    expect($owner->ownsTeam($team))->toBeTrue()
        ->and($other->ownsTeam($team))->toBeFalse();
});

it('renders the owner block only for the owner', function (): void {
    $owner = User::create();
    $team = Team::factory()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $output = Blade::render(
        '@teamOwner($team, $user) owner-only @endteamOwner',
        ['team' => $team, 'user' => $owner],
    );

    expect(trim($output))->toBe('owner-only');
});

it('hides the owner block for a non-owner', function (): void {
    $owner = User::create();
    $other = User::create();
    $team = Team::factory()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $output = Blade::render(
        '@teamOwner($team, $user) owner-only @endteamOwner',
        ['team' => $team, 'user' => $other],
    );

    expect(trim($output))->toBe('');
});

it('falls back to the authenticated user for the owner directive', function (): void {
    $owner = User::create();
    $team = Team::factory()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    auth()->setUser($owner);

    $output = Blade::render('@teamOwner($team) yes @endteamOwner', ['team' => $team]);

    expect(trim($output))->toBe('yes');
});
