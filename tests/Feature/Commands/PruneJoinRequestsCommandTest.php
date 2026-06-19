<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Models\JoinRequest;

it('auto-declines expired pending requests and reports the count', function (): void {
    JoinRequest::factory()->count(2)->expired()->create();
    JoinRequest::factory()->expiringWithin(5)->create();

    $exitCode = Artisan::call('teams:join-requests:prune');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Auto-declined 2 expired join request(s).')
        ->and(JoinRequest::query()->where('status', JoinRequestStatus::Denied->value)->count())->toBe(2);
});

it('is registered with the application', function (): void {
    expect(Artisan::all())->toHaveKey('teams:join-requests:prune');
});
