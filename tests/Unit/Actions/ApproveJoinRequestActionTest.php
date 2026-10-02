<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\ApproveJoinRequestAction;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestApproved;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotPendingException;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

function pendingRequest(Team $team, User $user, ?string $role = null): JoinRequest
{
    return JoinRequest::factory()->for($team)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'requested_role' => $role,
        'status' => JoinRequestStatus::Pending,
    ]);
}

it('adds the member, sets responder fields and fires an event', function (): void {
    Event::fake(JoinRequestApproved::class);

    $team = Team::factory()->create();
    $user = User::create();
    $admin = User::create();
    $request = pendingRequest($team, $user, 'admin');

    $member = app(ApproveJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(responder: $admin));

    expect($team->hasMember($user))->toBeTrue()
        ->and($member->role)->toBe('admin')
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Approved)
        ->and($request->fresh()?->responded_at)->not->toBeNull()
        ->and($request->fresh()?->responded_by_id)->toBe($admin->getKey());

    Event::assertDispatched(JoinRequestApproved::class);
});

it('falls back to the default role when none is requested', function (): void {
    config()->set('teams.roles.default', 'member');

    $team = Team::factory()->create();
    $user = User::create();
    $request = pendingRequest($team, $user);

    $member = app(ApproveJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(responder: User::create()));

    expect($member->role)->toBe('member');
});

it('allows the responder to override the role', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $request = pendingRequest($team, $user, 'member');

    $member = app(ApproveJoinRequestAction::class)->execute(
        $request,
        new RespondToJoinRequestData(responder: User::create(), role: 'admin'),
    );

    expect($member->role)->toBe('admin');
});

it('refuses to approve a request that was already denied', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $request = pendingRequest($team, $user);

    Teams::for($team)->joinRequests()->deny($request, by: User::create());

    Event::fake(JoinRequestApproved::class);

    expect(fn () => Teams::for($team)->joinRequests()->approve($request, by: User::create()))
        ->toThrow(JoinRequestNotPendingException::class);

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Denied)
        ->and($team->hasMember($user))->toBeFalse();
    Event::assertNotDispatched(JoinRequestApproved::class);
});

it('refuses to re-add a removed member by approving their old request', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $request = pendingRequest($team, $user);

    Teams::for($team)->joinRequests()->approve($request, by: User::create());
    Teams::for($team)->members()->remove($user);

    expect(fn () => Teams::for($team)->joinRequests()->approve($request, by: User::create()))
        ->toThrow(JoinRequestNotPendingException::class);

    expect($team->hasMember($user))->toBeFalse();
});

it('claims the pending status atomically, so a stale copy cannot approve twice', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $request = pendingRequest($team, $user, 'user');
    $stale = JoinRequest::query()->findOrFail($request->getKey());

    app(ApproveJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(responder: User::create()));
    $team->members()->delete();

    // The stale copy still reads "pending" in memory; the database says otherwise.
    expect($stale->isPending())->toBeTrue()
        ->and(fn () => app(ApproveJoinRequestAction::class)->execute($stale, new RespondToJoinRequestData(responder: User::create())))
        ->toThrow(JoinRequestNotPendingException::class);

    expect($team->hasMember($user))->toBeFalse();
});

it('leaves an existing member\'s role alone when their pending request is approved', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $request = pendingRequest($team, $user, 'admin');
    $team->addMember($user, 'user');

    $member = Teams::for($team)->joinRequests()->approve($request, by: User::create());

    expect($member->role)->toBe('user')
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Approved);
});

it('rolls the request back to pending when adding the member fails', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()->setMaxSeats(1);
    $team->addMember(User::create(), 'user');
    $request = pendingRequest($team, User::create());

    expect(fn () => Teams::for($team)->joinRequests()->approve($request, by: User::create()))
        ->toThrow(TeamsException::class, 'seat limit');

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Pending);
});
