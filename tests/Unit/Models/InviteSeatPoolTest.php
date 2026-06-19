<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('reports zero consumed seats for an unused single-use invite', function (): void {
    $invite = Invite::factory()->maxUses(1)->create();

    expect($invite->consumedSeats())->toBe(0)
        ->and($invite->remainingSeats())->toBe(1)
        ->and($invite->hasRemainingSeats())->toBeTrue();
});

it('tracks consumed and remaining seats across a partially consumed multi-use invite', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(5)->create();

    foreach (range(1, 3) as $_) {
        app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));
    }

    $invite->refresh();

    expect($invite->consumedSeats())->toBe(3)
        ->and($invite->remainingSeats())->toBe(2)
        ->and($invite->hasRemainingSeats())->toBeTrue();
});

it('reports a fully consumed multi-use invite as having no remaining seats', function (): void {
    $invite = Invite::factory()->maxUses(5)->create(['uses' => 5]);

    expect($invite->consumedSeats())->toBe(5)
        ->and($invite->remainingSeats())->toBe(0)
        ->and($invite->hasRemainingSeats())->toBeFalse();
});

it('never reports negative remaining seats', function (): void {
    $invite = Invite::factory()->maxUses(2)->create(['uses' => 4]);

    expect($invite->remainingSeats())->toBe(0)
        ->and($invite->hasRemainingSeats())->toBeFalse();
});

it('reports unlimited invites as having null remaining seats and always available', function (): void {
    $invite = Invite::factory()->maxUses(null)->create(['uses' => 99]);

    expect($invite->consumedSeats())->toBe(99)
        ->and($invite->remainingSeats())->toBeNull()
        ->and($invite->hasRemainingSeats())->toBeTrue();
});

it('exposes its accepting members as the seat-pool roster', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(5)->create();

    $first = app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));
    $second = app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));

    $roster = $invite->acceptedMembers()->pluck('id')->all();

    expect($roster)->toContain($first->getKey())
        ->and($roster)->toContain($second->getKey())
        ->and($invite->acceptedMembers()->count())->toBe(2);
});

it('exposes consumers as an alias of acceptedMembers', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(5)->create();

    app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));

    expect($invite->consumers()->count())->toBe($invite->acceptedMembers()->count())
        ->and($invite->consumers()->first())->toBeInstanceOf(Member::class);
});

it('isolates the roster between two distinct invites', function (): void {
    $team = Team::factory()->create();
    $inviteA = Invite::factory()->for($team)->maxUses(5)->create();
    $inviteB = Invite::factory()->for($team)->maxUses(5)->create();

    $memberA = app(AcceptInviteAction::class)->execute($inviteA, new AcceptInviteData(member: User::create()));
    $memberB = app(AcceptInviteAction::class)->execute($inviteB, new AcceptInviteData(member: User::create()));

    expect($inviteA->acceptedMembers()->pluck('id')->all())->toBe([$memberA->getKey()])
        ->and($inviteB->acceptedMembers()->pluck('id')->all())->toBe([$memberB->getKey()]);
});
