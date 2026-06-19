<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\ApproveJoinRequestAction;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestApproved;
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

it('is a no-op on a non-pending request but still returns the membership', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $request = pendingRequest($team, $user, 'admin');
    $team->addMember($user, 'admin');

    Event::fake(JoinRequestApproved::class);

    $request->update(['status' => JoinRequestStatus::Approved]);

    $member = app(ApproveJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(responder: User::create()));

    expect($member->role)->toBe('admin');
    Event::assertNotDispatched(JoinRequestApproved::class);
});
