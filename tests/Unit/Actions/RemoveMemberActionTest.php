<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\RemoveMemberAction;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('removes a member and dispatches the event', function () {
    Event::fake(TeamMemberDeleted::class);

    $team = Team::factory()->create();
    $user = User::create();
    $member = $team->addMember($user, 'admin');

    expect(app(RemoveMemberAction::class)->execute($team, $user))->toBeTrue();

    Event::assertDispatched(fn (TeamMemberDeleted $e) => $e->member->is($member));
    $this->assertSoftDeleted($member);
});

it('returns false when the member is not on the team', function () {
    Event::fake(TeamMemberDeleted::class);

    $team = Team::factory()->create();
    $user = User::create();

    expect(app(RemoveMemberAction::class)->execute($team, $user))->toBeFalse();

    Event::assertNotDispatched(TeamMemberDeleted::class);
});
