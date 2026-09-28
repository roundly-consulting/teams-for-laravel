<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Testing\TeamExpectations;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    Teams::roles()->register('editor', 'Editor', ['posts.publish']);
    TeamExpectations::register();
});

/**
 * Run a matcher and report whether it raised an assertion failure. Pest's
 * toThrow() cannot catch assertion failures, so we capture them by hand.
 */
function matcherFails(Closure $matcher): bool
{
    try {
        $matcher();
    } catch (AssertionFailedError) {
        return true;
    }

    return false;
}

it('passes the membership matchers for a member', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'editor');

    expect($user)
        ->toBeMemberOf($team)
        ->withRole($team, 'editor')
        ->toHaveTeamPermission($team, 'posts.publish');
});

it('fails toBeMemberOf for a non-member', function (): void {
    $team = Team::factory()->create();
    $user = User::create();

    expect(matcherFails(fn () => expect($user)->toBeMemberOf($team)))->toBeTrue();
});

it('fails withRole for a mismatched role', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'editor');

    expect(matcherFails(fn () => expect($user)->withRole($team, 'owner')))->toBeTrue();
});

it('fails toHaveTeamPermission for a missing permission', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'editor');

    expect(matcherFails(fn () => expect($user)->toHaveTeamPermission($team, 'posts.delete')))->toBeTrue();
});
