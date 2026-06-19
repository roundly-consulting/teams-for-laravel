<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('nulls accepted_invite_id on members when the invite is force-deleted', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->maxUses(5)->create();

    $member = app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: User::create()));

    expect($member->accepted_invite_id)->toBe($invite->getKey());

    $invite->forceDelete();

    $member->refresh();

    expect(Member::query()->whereKey($member->getKey())->exists())->toBeTrue()
        ->and($member->accepted_invite_id)->toBeNull();
});
