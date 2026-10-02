<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\Events\InviteAccepted;
use RoundlyConsulting\Teams\Exceptions\InviteEmailMismatchException;
use RoundlyConsulting\Teams\Exceptions\InviteExpiredException;
use RoundlyConsulting\Teams\Exceptions\InviteNotFoundException;
use RoundlyConsulting\Teams\Facades\Teams;
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

it('stamps the accepted invite on the new membership', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->maxUses(5)->create();

    $member = app(AcceptInviteAction::class)->execute($invite, new AcceptInviteData(member: $user));

    expect($member->accepted_invite_id)->toBe($invite->getKey())
        ->and($member->acceptedInvite->is($invite))->toBeTrue();
});

it('matches an email-targeted invite case-insensitively', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Teams::for($team)->invites()->create(role: 'user', email: 'Jane@Acme.test');

    $member = Teams::invites()->accept($invite, $user, email: 'jane@acme.TEST');

    expect($member->role)->toBe('user')
        ->and(Invite::query()->forEmail('JANE@acme.test')->withTrashed()->count())->toBe(1);
});

it('refuses an invite whose team was deleted with a package exception', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->create();
    $team->delete();

    expect(fn () => Teams::invites()->accept($invite, $user))
        ->toThrow(InviteNotFoundException::class);

    expect(Member::query()->count())->toBe(0);
});
