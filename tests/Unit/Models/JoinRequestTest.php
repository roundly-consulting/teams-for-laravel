<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;

it('casts status to the enum', function (): void {
    $request = JoinRequest::factory()->create(['status' => JoinRequestStatus::Pending]);

    expect($request->status)->toBe(JoinRequestStatus::Pending);
});

it('exposes its relationships', function (): void {
    expect((new JoinRequest)->team())->toBeInstanceOf(BelongsTo::class)
        ->and((new JoinRequest)->requester())->toBeInstanceOf(MorphTo::class)
        ->and((new JoinRequest)->respondedBy())->toBeInstanceOf(MorphTo::class);
});

it('scopes to pending requests', function (): void {
    $team = Team::factory()->create();
    JoinRequest::factory()->for($team)->create(['status' => JoinRequestStatus::Pending]);
    JoinRequest::factory()->for($team)->create(['status' => JoinRequestStatus::Denied]);

    expect(JoinRequest::query()->pending()->count())->toBe(1);
});

it('reports whether it is pending', function (): void {
    expect((new JoinRequest(['status' => JoinRequestStatus::Pending]))->isPending())->toBeTrue()
        ->and((new JoinRequest(['status' => JoinRequestStatus::Approved]))->isPending())->toBeFalse();
});

it('uses its factory', function (): void {
    expect(JoinRequest::factory()->make())->toBeInstanceOf(JoinRequest::class);
});
