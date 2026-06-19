<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\Events\InviteAccepted;
use RoundlyConsulting\Teams\Exceptions\InviteEmailMismatchException;
use RoundlyConsulting\Teams\Exceptions\InviteExpiredException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('accepts a valid invite and adds the member', function () {
    Event::fake(InviteAccepted::class);

    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->create(['role' => 'admin']);

    $member = app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: $user));

    expect($member)->toBeInstanceOf(Member::class)->role->toBe('admin')
        ->and($team->hasMember($user))->toBeTrue();

    $this->assertSoftDeleted($invite);
    Event::assertDispatched(fn (InviteAccepted $e) => $e->invite->is($invite) && $e->member->is($member));
});

it('throws when the invite has expired', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->expired()->create();

    expect(fn () => app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: $user)))
        ->toThrow(InviteExpiredException::class);

    expect($team->hasMember($user))->toBeFalse();
});

it('throws when the invite email does not match', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->forEmail('jane@acme.test')->create();

    expect(fn () => app(AcceptInviteAction::class)->execute(
        $invite,
        new AcceptInviteData(member: $user, email: 'someone@else.test'),
    ))->toThrow(InviteEmailMismatchException::class);
});

it('accepts an email-targeted invite when the email matches', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->forEmail('jane@acme.test')->create(['role' => 'user']);

    $member = app(AcceptInviteAction::class)->execute(
        $invite,
        new AcceptInviteData(member: $user, email: 'jane@acme.test'),
    );

    expect($member->role)->toBe('user');
});
