<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\RequestToJoinAction;
use RoundlyConsulting\Teams\DataTransferObjects\RequestToJoinData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestCreated;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('creates a pending request and fires an event', function (): void {
    Event::fake(JoinRequestCreated::class);

    $team = Team::factory()->create();
    $user = User::create();

    $request = app(RequestToJoinAction::class)->execute(new RequestToJoinData(
        team: $team,
        requester: $user,
        message: 'Please add me',
    ));

    expect($request)->toBeInstanceOf(JoinRequest::class)
        ->status->toBe(JoinRequestStatus::Pending)
        ->message->toBe('Please add me');

    Event::assertDispatched(fn (JoinRequestCreated $e) => $e->joinRequest->is($request));
});

it('returns the existing pending request on a repeat call', function (): void {
    $team = Team::factory()->create();
    $user = User::create();

    $action = app(RequestToJoinAction::class);

    $first = $action->execute(new RequestToJoinData(team: $team, requester: $user));
    $second = $action->execute(new RequestToJoinData(team: $team, requester: $user));

    expect($second->is($first))->toBeTrue()
        ->and(JoinRequest::query()->count())->toBe(1);
});
