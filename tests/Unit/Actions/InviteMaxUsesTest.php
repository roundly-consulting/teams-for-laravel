<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\Exceptions\InviteExhaustedException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('deletes a single-use invite after one accept', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(1)->create();

    app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));

    $this->assertSoftDeleted($invite);
});

it('allows multiple accepts up to max_uses then exhausts', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(3)->create();

    foreach (range(1, 3) as $_) {
        app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));
    }

    expect($invite->uses)->toBe(3)
        ->and(Invite::query()->whereKey($invite->getKey())->exists())->toBeFalse()
        ->and(Invite::withTrashed()->whereKey($invite->getKey())->exists())->toBeTrue();
});

it('keeps a multi-use invite alive until exhausted', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(2)->create();

    app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));

    expect($invite->fresh()?->uses)->toBe(1);
    expect($invite->fresh()?->trashed())->toBeFalse();
});

it('throws when an exhausted invite is accepted again', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(1)->create(['uses' => 1]);

    expect(fn () => app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create())))
        ->toThrow(InviteExhaustedException::class);
});

it('treats a null max_uses as unlimited', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(null)->create();

    app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));

    expect($invite->fresh()?->trashed())->toBeFalse()
        ->and($invite->fresh()?->uses)->toBe(1);
});
