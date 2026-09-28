<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestApproved;
use RoundlyConsulting\Teams\Events\JoinRequestDenied;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    config()->set('teams.approvals.enabled', true);
});

it('opens an approval request and leaves the join request pending', function (): void {
    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();

    $request = Teams::for($team)
        ->joinRequests()->requireApprovalFrom($admin)
        ->open($user);

    expect($request->status)->toBe(JoinRequestStatus::Pending)
        ->and($request->approvalRequests()->count())->toBe(1)
        ->and($team->hasMember($user))->toBeFalse();
});

it('adds the member and marks approved when the engine approves', function (): void {
    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();
    $request = Teams::for($team)->joinRequests()->requireApprovalFrom($admin)->open($user);

    $captured = [];
    Event::listen(JoinRequestApproved::class, function (JoinRequestApproved $e) use (&$captured): void {
        $captured[] = $e->joinRequest->getKey();
    });

    Approvals::for($request)->as($admin)->approve();

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved)
        ->and($team->hasMember($user))->toBeTrue()
        ->and($captured)->toBe([$request->getKey()]);
});

it('marks denied and dispatches the denied event when the engine rejects', function (): void {
    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();
    $request = Teams::for($team)->joinRequests()->requireApprovalFrom($admin)->open($user);

    $captured = [];
    Event::listen(JoinRequestDenied::class, function (JoinRequestDenied $e) use (&$captured): void {
        $captured[] = $e->joinRequest->getKey();
    });

    Approvals::for($request)->as($admin)->reject();

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Denied)
        ->and($team->hasMember($user))->toBeFalse()
        ->and($captured)->toBe([$request->getKey()]);
});

it('holds pending until a quorum is reached', function (): void {
    $team = Team::factory()->create();
    $a = User::create();
    $b = User::create();
    $user = User::create();

    $request = Teams::for($team)
        ->joinRequests()->requireApprovalFrom([$a, $b])
        ->rule(ApprovalRule::Quorum)
        ->quorum(2)
        ->open($user);

    Approvals::for($request)->as($a)->approve();
    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Pending);

    Approvals::for($request)->as($b)->approve();
    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved);
});

it('is idempotent on a double resolution', function (): void {
    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();
    $request = Teams::for($team)->joinRequests()->requireApprovalFrom($admin)->open($user);

    Approvals::for($request)->as($admin)->approve();
    Approvals::for($request)->as($admin)->approve();

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved)
        ->and($team->members()->count())->toBe(1);
});

it('records the deciding admin as the responder', function (): void {
    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();
    $request = Teams::for($team)->joinRequests()->requireApprovalFrom($admin)->open($user);

    Approvals::for($request)->as($admin)->approve();

    $fresh = $request->fresh();

    expect((string) $fresh?->responded_by_id)->toBe((string) $admin->getKey())
        ->and($fresh?->responded_by_type)->toBe($admin->getMorphClass());
});

it('ignores a resolution whose subject is not a join request', function (): void {
    $user = User::create();

    $request = new ApprovalRequest;
    $request->subject_id = $user->getKey();
    $request->subject_type = $user->getMorphClass();
    $request->rule = ApprovalRule::Unanimous;
    $request->required_approvers = 1;
    $request->status = ApprovalStatus::Approved;
    $request->save();

    event(new ApprovalRequestResolved($request));
})->throwsNoExceptions();

it('is a no-op for a cancelled or expired approval', function (): void {
    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();
    $request = Teams::for($team)->joinRequests()->requireApprovalFrom($admin)->open($user);

    $approvalRequest = $request->approvalRequests()->first();
    $approvalRequest->status = ApprovalStatus::Cancelled;
    $approvalRequest->save();

    event(new ApprovalRequestResolved($approvalRequest));

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Pending);
});

it('does not sync while approvals are disabled', function (): void {
    config()->set('teams.approvals.enabled', false);

    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();

    // With the flag off the handle never opens an approval request; the
    // request stays on the native single-responder path.
    $request = Teams::for($team)->joinRequests()->requireApprovalFrom($admin)->open($user);

    expect($request->approvalRequests()->count())->toBe(0)
        ->and($request->status)->toBe(JoinRequestStatus::Pending);

    // The native path still resolves it.
    Teams::for($team)->joinRequests()->approve($request, by: $admin);

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved)
        ->and($team->hasMember($user))->toBeTrue();
});

it('keeps the native single-responder approve path working alongside approvals', function (): void {
    $team = Team::factory()->create();
    $responder = User::create();
    $user = User::create();

    // A plain request with no approvers uses the native path even when the
    // feature flag is enabled.
    $request = Teams::for($team)->joinRequests()->open($user);

    Teams::for($team)->joinRequests()->approve($request, by: $responder);

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved)
        ->and($team->hasMember($user))->toBeTrue()
        ->and($request->approvalRequests()->count())->toBe(0);
});

it('does not open a second approval request when the requester asks again', function (): void {
    $team = Team::factory()->create();
    $admin = User::create();
    $user = User::create();
    $staged = Teams::for($team)->joinRequests()->requireApprovalFrom($admin);

    $first = $staged->open($user);
    $again = $staged->open($user);

    expect($again->is($first))->toBeTrue()
        ->and($first->approvalRequests()->count())->toBe(1);
});

it('uses the configured rule and quorum when the handle sets none', function (): void {
    config()->set('teams.approvals.rule', 'quorum');
    config()->set('teams.approvals.quorum', 2);

    $team = Team::factory()->create();
    $a = User::create();
    $b = User::create();
    $user = User::create();

    $request = Teams::for($team)->joinRequests()->requireApprovalFrom(collect([$a, $b]))->open($user);

    expect($request->approvalRequests()->first()?->rule)->toBe(ApprovalRule::Quorum)
        ->and($request->approvalRequests()->first()?->required_approvers)->toBe(2);
});
