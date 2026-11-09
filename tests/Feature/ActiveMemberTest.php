<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Testing\TeamExpectations;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::create();
});

it('counts an active membership as an active member', function (): void {
    Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->addDay());

    expect($this->team->hasActiveMember($this->user))->toBeTrue()
        ->and(Teams::for($this->team)->members()->hasActive($this->user))->toBeTrue()
        ->and($this->user->isActiveMemberOf($this->team))->toBeTrue();
});

it('does not count an expired membership — hasMember() still sees the audit row', function (): void {
    Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->addDay());
    $this->travel(2)->days();

    expect($this->team->hasActiveMember($this->user))->toBeFalse()
        ->and(Teams::for($this->team)->members()->hasActive($this->user))->toBeFalse()
        ->and($this->user->isActiveMemberOf($this->team))->toBeFalse()
        // Unchanged (deprecated) row checks: an expired row stays until pruned.
        ->and($this->team->hasMember($this->user))->toBeTrue()
        ->and(Teams::for($this->team)->members()->has($this->user))->toBeTrue()
        ->and($this->user->isMemberOf($this->team))->toBeTrue();
});

it('does not count a non-member or a removed member', function (): void {
    expect($this->team->hasActiveMember($this->user))->toBeFalse();

    Teams::for($this->team)->members()->add($this->user, 'user');
    Teams::for($this->team)->members()->remove($this->user);

    expect($this->team->hasActiveMember($this->user))->toBeFalse();
});

it('counts only a direct membership, unlike isAccessibleBy()', function (): void {
    config()->set('teams.nesting.enabled', true);
    config()->set('teams.nesting.inherit_roles_down', true);

    $squad = Team::factory()->create();
    Teams::for($squad)->moveUnder($this->team);
    Teams::for($this->team)->members()->add($this->user, 'user');

    expect($squad->hasActiveMember($this->user))->toBeFalse()
        ->and($squad->isAccessibleBy($this->user))->toBeTrue();
});

it('keeps toBeMemberOf() a row check', function (): void {
    TeamExpectations::register();

    Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->subMinute());

    expect($this->user)->toBeMemberOf($this->team);
    expect(fn () => expect(User::create())->toBeMemberOf($this->team))->toThrow(Exception::class);
});
