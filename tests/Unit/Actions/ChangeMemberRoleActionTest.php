<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\ChangeMemberRoleAction;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Exceptions\MemberNotFoundException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('changes a member role and dispatches the event', function () {
    Event::fake(TeamMemberRoleChanged::class);

    $team = Team::factory()->create();
    $user = User::create();
    $member = $team->addMember($user, 'user');

    $updated = app(ChangeMemberRoleAction::class)->execute($team, $user, 'admin');

    expect($updated->role)->toBe('admin');

    Event::assertDispatched(fn (TeamMemberRoleChanged $e) => $e->member->is($member) && $e->previousRole === 'user');
});

it('does nothing when the role is unchanged', function () {
    Event::fake(TeamMemberRoleChanged::class);

    $team = Team::factory()->create();
    $user = User::create();
    $member = $team->addMember($user, 'admin');

    app(ChangeMemberRoleAction::class)->execute($team, $user, 'admin');

    Event::assertNotDispatched(TeamMemberRoleChanged::class);
});

it('refuses a model that is not a member of the team', function () {
    $team = Team::factory()->create();
    $other = Team::factory()->create();
    $user = User::create();
    $other->addMember($user, 'user');

    expect(fn () => app(ChangeMemberRoleAction::class)->execute($team, $user, 'admin'))
        ->toThrow(MemberNotFoundException::class);

    expect($other->memberHasRole($user, 'user'))->toBeTrue();
});
