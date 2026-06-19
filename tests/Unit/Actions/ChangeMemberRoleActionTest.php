<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\ChangeMemberRoleAction;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('changes a member role and dispatches the event', function () {
    Event::fake(TeamMemberRoleChanged::class);

    $team = Team::factory()->create();
    $user = User::create();
    $member = $team->addMember($user, 'user');

    $updated = app(ChangeMemberRoleAction::class)->execute($member, 'admin');

    expect($updated->role)->toBe('admin');

    Event::assertDispatched(fn (TeamMemberRoleChanged $e) => $e->member->is($member) && $e->previousRole === 'user');
});

it('does nothing when the role is unchanged', function () {
    Event::fake(TeamMemberRoleChanged::class);

    $team = Team::factory()->create();
    $user = User::create();
    $member = $team->addMember($user, 'admin');

    app(ChangeMemberRoleAction::class)->execute($member, 'admin');

    Event::assertNotDispatched(TeamMemberRoleChanged::class);
});
