<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Models\JoinRequest;

it('reports isExpired for a past expiry', function (): void {
    $request = JoinRequest::factory()->expired()->create();

    expect($request->isExpired())->toBeTrue();
});

it('reports not expired for a future expiry', function (): void {
    $request = JoinRequest::factory()->expiringWithin(5)->create();

    expect($request->isExpired())->toBeFalse();
});

it('reports not expired for a null expiry', function (): void {
    $request = JoinRequest::factory()->create(['expires_at' => null]);

    expect($request->isExpired())->toBeFalse();
});

it('scopes to expired pending requests only', function (): void {
    $expiredPending = JoinRequest::factory()->expired()->create();
    JoinRequest::factory()->expiringWithin(5)->create();              // future pending
    JoinRequest::factory()->create(['expires_at' => null]);          // never expires
    JoinRequest::factory()->expired()->create([                      // expired but resolved
        'status' => JoinRequestStatus::Denied,
    ]);

    $ids = JoinRequest::query()->expiredPending()->pluck('id')->all();

    expect($ids)->toBe([$expiredPending->getKey()]);
});

it('marks resolved rows past the retention window as prunable', function (): void {
    config()->set('teams.join_requests.prune_after', '30 days');

    $stale = JoinRequest::factory()->create([
        'status' => JoinRequestStatus::Denied,
        'updated_at' => now()->subMonths(2),
    ]);
    JoinRequest::factory()->create([                                 // resolved but recent
        'status' => JoinRequestStatus::Approved,
        'updated_at' => now(),
    ]);
    JoinRequest::factory()->create([                                 // pending, never pruned
        'status' => JoinRequestStatus::Pending,
        'updated_at' => now()->subMonths(2),
    ]);

    $ids = (new JoinRequest)->prunable()->pluck('id')->all();

    expect($ids)->toBe([$stale->getKey()]);
});
