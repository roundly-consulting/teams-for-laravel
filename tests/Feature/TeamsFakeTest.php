<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Testing\RecordedTeamOperation;
use RoundlyConsulting\Teams\Testing\TeamsFake;
use RoundlyConsulting\Teams\Tests\User;

function fakeFails(Closure $assertion): void
{
    expect($assertion)->toThrow(AssertionFailedError::class);
}

function fakeJoinRequest(Team $team, User $user): JoinRequest
{
    return JoinRequest::factory()->for($team)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'status' => JoinRequestStatus::Pending,
    ]);
}

it('swaps a subtype of the manager into the facade and the container', function (): void {
    $fake = Teams::fake();

    expect($fake)->toBeInstanceOf(TeamsFake::class)
        ->toBeInstanceOf(TeamsManager::class)
        ->and(app(TeamsManager::class))->toBe($fake)
        ->and(Teams::getFacadeRoot())->toBe($fake);
});

it('still performs every operation', function (): void {
    Teams::fake();

    $team = Teams::create(new CreateTeamData(name: 'Acme'));

    expect(Team::query()->whereKey($team->getKey())->exists())->toBeTrue();
});

it('records calls made through the facade, an injected manager, the model methods and the commands', function (): void {
    $fake = Teams::fake();
    $team = Team::factory()->create();
    $a = User::create();
    $b = User::create();

    Teams::for($team)->members()->add($a, 'user');
    app(TeamsManager::class)->for($team)->members()->add($b, 'user');
    $team->addMember(User::create(), 'admin');
    test()->artisan('teams:invites:prune')->assertSuccessful();

    expect($fake->recorded(TeamOperation::AddMember))->toHaveCount(3)
        ->and($fake->recorded(TeamOperation::PruneInvites))->toHaveCount(1)
        ->and($fake->recorded())->toHaveCount(4)
        ->and($fake->recorded()[0])->toBeInstanceOf(RecordedTeamOperation::class)
        ->and($fake->recorded()[0]->result->role)->toBe('user');
});

it('records join-request decisions driven by the approvals engine', function (): void {
    config()->set('teams.approvals.enabled', true);
    $fake = Teams::fake();
    $team = Team::factory()->create();
    $admin = User::create();

    $request = Teams::for($team)->joinRequests()->requireApprovalFrom($admin)->open(User::create());
    Approvals::for($request)->as($admin)->approve();

    $fake->assertJoinRequestApproved($request, $admin);
});

it('does not record a refused operation', function (): void {
    $fake = Teams::fake();
    $mine = Team::factory()->create();
    $invite = Invite::factory()->for(Team::factory())->create();

    expect(fn () => Teams::for($mine)->invites()->revoke($invite))->toThrow(Exception::class);

    $fake->assertNothingRevoked();
    $fake->assertNothingRecorded();
});

describe('assertions', function (): void {
    it('assertNothingRecorded', function (): void {
        $fake = Teams::fake();
        $fake->assertNothingRecorded();

        Teams::create(new CreateTeamData(name: 'Acme'));

        fakeFails(fn () => $fake->assertNothingRecorded());
    });

    it('assertTeamCreated / assertNothingCreated', function (): void {
        $fake = Teams::fake();
        $fake->assertNothingCreated();
        fakeFails(fn () => $fake->assertTeamCreated());

        Teams::create(new CreateTeamData(name: 'Acme'));

        $fake->assertTeamCreated();
        $fake->assertTeamCreated('Acme');
        fakeFails(fn () => $fake->assertTeamCreated('Other'));
        fakeFails(fn () => $fake->assertNothingCreated());
    });

    it('assertOwnershipTransferred / assertNothingTransferred', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $owner = User::create();
        $fake->assertNothingTransferred();
        fakeFails(fn () => $fake->assertOwnershipTransferred($team));

        Teams::for($team)->transferOwnershipTo($owner);

        $fake->assertOwnershipTransferred($team);
        $fake->assertOwnershipTransferred($team, $owner);
        fakeFails(fn () => $fake->assertOwnershipTransferred($team, User::create()));
        fakeFails(fn () => $fake->assertNothingTransferred());
    });

    it('assertMemberAdded / assertMemberNotAdded / assertNothingAdded — through the model', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $user = User::create();
        $fake->assertNothingAdded();
        $fake->assertMemberNotAdded($team, $user);
        fakeFails(fn () => $fake->assertMemberAdded($team, $user));

        $team->addMember($user, 'admin');

        $fake->assertMemberAdded($team, $user);
        $fake->assertMemberAdded($team, $user, 'admin');
        fakeFails(fn () => $fake->assertMemberAdded($team, $user, 'user'));
        fakeFails(fn () => $fake->assertMemberAdded(Team::factory()->create(), $user));
        fakeFails(fn () => $fake->assertMemberNotAdded($team, $user));
        fakeFails(fn () => $fake->assertNothingAdded());
    });

    it('assertMemberRemoved / assertNothingRemoved — through the membership model', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $user = User::create();
        $membership = $team->addMember($user, 'user');
        $fake->assertNothingRemoved();
        fakeFails(fn () => $fake->assertMemberRemoved($team, $user));

        $membership->removeFromTeam();

        $fake->assertMemberRemoved($team, $user);
        fakeFails(fn () => $fake->assertMemberRemoved($team, User::create()));
        fakeFails(fn () => $fake->assertNothingRemoved());
    });

    it('assertRoleChanged / assertNoRoleChanged', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $user = User::create();
        $team->addMember($user, 'user');
        $fake->assertNoRoleChanged();
        fakeFails(fn () => $fake->assertRoleChanged($team, $user));

        Teams::for($team)->members()->changeRole($user, 'admin');

        $fake->assertRoleChanged($team, $user);
        $fake->assertRoleChanged($team, $user, 'admin');
        fakeFails(fn () => $fake->assertRoleChanged($team, $user, 'owner'));
        fakeFails(fn () => $fake->assertNoRoleChanged());
    });

    it('assertExpiringMembersNotified / assertNothingNotified', function (): void {
        $fake = Teams::fake();
        Teams::members()->expiring();
        $fake->assertNothingNotified();
        fakeFails(fn () => $fake->assertExpiringMembersNotified());

        Teams::members()->notifyExpiring();

        $fake->assertExpiringMembersNotified();
        fakeFails(fn () => $fake->assertNothingNotified());
    });

    it('assertInviteCreated / assertNothingInvited — through the model', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $fake->assertNothingInvited();
        fakeFails(fn () => $fake->assertInviteCreated($team));

        $team->invite('user', email: 'jo@acme.test');

        $fake->assertInviteCreated($team);
        $fake->assertInviteCreated($team, 'jo@acme.test');
        fakeFails(fn () => $fake->assertInviteCreated($team, 'other@acme.test'));
        fakeFails(fn () => $fake->assertNothingInvited());
    });

    it('assertInviteResent / assertNothingResent — through the model', function (): void {
        $fake = Teams::fake();
        $invite = Invite::factory()->for(Team::factory())->create();
        $fake->assertNothingResent();
        fakeFails(fn () => $fake->assertInviteResent($invite));

        $invite->resend();

        $fake->assertInviteResent($invite);
        fakeFails(fn () => $fake->assertInviteResent(Invite::factory()->for(Team::factory())->create()));
        fakeFails(fn () => $fake->assertNothingResent());
    });

    it('assertInviteRevoked / assertNothingRevoked — through the model', function (): void {
        $fake = Teams::fake();
        $invite = Invite::factory()->for(Team::factory())->create();
        $fake->assertNothingRevoked();
        fakeFails(fn () => $fake->assertInviteRevoked($invite));

        $invite->revoke();

        $fake->assertInviteRevoked($invite);
        fakeFails(fn () => $fake->assertNothingRevoked());
    });

    it('assertInviteAccepted / assertNothingAccepted — through the model', function (): void {
        $fake = Teams::fake();
        $invite = Invite::factory()->for(Team::factory())->create(['max_uses' => null]);
        $user = User::create();
        $fake->assertNothingAccepted();
        fakeFails(fn () => $fake->assertInviteAccepted());

        $invite->acceptBy($user);

        $fake->assertInviteAccepted();
        $fake->assertInviteAccepted($invite);
        $fake->assertInviteAccepted($invite, $user);
        fakeFails(fn () => $fake->assertInviteAccepted($invite, User::create()));
        fakeFails(fn () => $fake->assertNothingAccepted());
    });

    it('assertJoinRequested / assertNothingRequested', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $user = User::create();
        $fake->assertNothingRequested();
        fakeFails(fn () => $fake->assertJoinRequested($team));

        Teams::for($team)->joinRequests()->open($user);

        $fake->assertJoinRequested($team);
        $fake->assertJoinRequested($team, $user);
        fakeFails(fn () => $fake->assertJoinRequested($team, User::create()));
        fakeFails(fn () => $fake->assertNothingRequested());
    });

    it('assertJoinRequestApproved / assertNothingApproved', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $request = fakeJoinRequest($team, User::create());
        $admin = User::create();
        $fake->assertNothingApproved();
        fakeFails(fn () => $fake->assertJoinRequestApproved($request));

        Teams::for($team)->joinRequests()->approve($request, by: $admin);

        $fake->assertJoinRequestApproved($request);
        $fake->assertJoinRequestApproved($request, $admin);
        fakeFails(fn () => $fake->assertJoinRequestApproved($request, User::create()));
        fakeFails(fn () => $fake->assertNothingApproved());
    });

    it('assertJoinRequestDenied / assertNothingDenied', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $request = fakeJoinRequest($team, User::create());
        $admin = User::create();
        $fake->assertNothingDenied();
        fakeFails(fn () => $fake->assertJoinRequestDenied($request));

        Teams::for($team)->joinRequests()->deny($request, by: $admin);

        $fake->assertJoinRequestDenied($request, $admin);
        fakeFails(fn () => $fake->assertJoinRequestDenied(fakeJoinRequest($team, User::create())));
        fakeFails(fn () => $fake->assertNothingDenied());
    });

    it('assertRoleDefined / assertNothingDefined — through the model', function (): void {
        $fake = Teams::fake();
        $team = Team::factory()->create();
        $fake->assertNothingDefined();
        fakeFails(fn () => $fake->assertRoleDefined($team));

        $team->defineRole('editor', 'Editor');

        $fake->assertRoleDefined($team);
        $fake->assertRoleDefined($team, 'editor');
        fakeFails(fn () => $fake->assertRoleDefined($team, 'viewer'));
        fakeFails(fn () => $fake->assertNothingDefined());
    });

    it('assertInvitesPruned / assertMembersPruned / assertJoinRequestsExpired / assertNothingPruned', function (): void {
        $fake = Teams::fake();
        $fake->assertNothingPruned();
        fakeFails(fn () => $fake->assertInvitesPruned());
        fakeFails(fn () => $fake->assertMembersPruned());
        fakeFails(fn () => $fake->assertJoinRequestsExpired());

        Teams::invites()->prune();
        Teams::members()->prune();
        Teams::joinRequests()->expire();

        $fake->assertInvitesPruned();
        $fake->assertMembersPruned();
        $fake->assertJoinRequestsExpired();
        fakeFails(fn () => $fake->assertNothingPruned());
    });
});
