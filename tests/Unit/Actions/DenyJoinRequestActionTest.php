<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\DenyJoinRequestAction;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestDenied;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotPendingException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('denies a pending request without creating a membership', function (): void {
    Event::fake(JoinRequestDenied::class);

    $team = Team::factory()->create();
    $user = User::create();
    $admin = User::create();
    $request = JoinRequest::factory()->for($team)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'status' => JoinRequestStatus::Pending,
    ]);

    app(DenyJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(responder: $admin));

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Denied)
        ->and($request->fresh()?->responded_by_id)->toBe($admin->getKey())
        ->and($team->hasMember($user))->toBeFalse();

    Event::assertDispatched(JoinRequestDenied::class);
});

it('refuses to deny a request that is no longer pending', function (): void {
    Event::fake(JoinRequestDenied::class);

    $request = JoinRequest::factory()->create(['status' => JoinRequestStatus::Approved]);

    expect(fn () => app(DenyJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(responder: User::create())))
        ->toThrow(JoinRequestNotPendingException::class);

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved);
    Event::assertNotDispatched(JoinRequestDenied::class);
});

it('cannot deny a stale copy of a request that was approved meanwhile', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $request = JoinRequest::factory()->for($team)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'status' => JoinRequestStatus::Pending,
    ]);
    $stale = JoinRequest::query()->findOrFail($request->getKey());

    Teams::for($team)->joinRequests()->approve($request, by: User::create());

    expect(fn () => Teams::for($team)->joinRequests()->deny($stale, by: User::create()))
        ->toThrow(JoinRequestNotPendingException::class);

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved)
        ->and($team->hasMember($user))->toBeTrue();
});
