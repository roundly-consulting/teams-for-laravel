<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Exceptions\InviteExpiredException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('rejects an expired invite when accepting through the model', function () {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->expired()->create();
    $user = User::create();

    expect(fn () => $invite->acceptBy($user))->toThrow(InviteExpiredException::class)
        ->and($team->hasMember($user))->toBeFalse();
});

it('accepts an email-targeted invite via the model when the email matches', function () {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->forEmail('jane@acme.test')->create(['role' => 'admin']);
    $user = User::create();

    $member = $invite->acceptBy($user, 'jane@acme.test');

    expect($member->role)->toBe('admin');
});

it('reports exhaustion based on uses and max_uses', function () {
    expect((new Invite(['uses' => 0, 'max_uses' => 1]))->isExhausted())->toBeFalse()
        ->and((new Invite(['uses' => 1, 'max_uses' => 1]))->isExhausted())->toBeTrue()
        ->and((new Invite(['uses' => 5, 'max_uses' => null]))->isExhausted())->toBeFalse();
});
