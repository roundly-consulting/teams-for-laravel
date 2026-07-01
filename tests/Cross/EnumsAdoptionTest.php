<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Models\JoinRequest;

it('exposes the enums helper option list', function (): void {
    $options = JoinRequestStatus::options();

    expect($options)->toHaveCount(3)
        ->and($options->pluck('value')->all())->toBe(['pending', 'approved', 'denied']);
});

it('exposes readable labels for every case', function (): void {
    expect(JoinRequestStatus::labels()->all())->toBe(['Pending', 'Approved', 'Denied'])
        ->and(JoinRequestStatus::Denied->readable())->toBe('Denied');
});

it('builds a validation rule that accepts a valid value and rejects an invalid one', function (): void {
    $rule = JoinRequestStatus::validationRule();

    expect(Validator::make(['status' => 'approved'], ['status' => $rule])->passes())->toBeTrue()
        ->and(Validator::make(['status' => 'nope'], ['status' => $rule])->passes())->toBeFalse();
});

it('resolves cases by label and value', function (): void {
    expect(JoinRequestStatus::tryFromLabel('Approved'))->toBe(JoinRequestStatus::Approved)
        ->and(JoinRequestStatus::tryFromLabel('Unknown'))->toBeNull()
        ->and(JoinRequestStatus::values()->all())->toBe(['pending', 'approved', 'denied']);
});

it('still round-trips the status cast on a join request', function (): void {
    $request = JoinRequest::factory()->create(['status' => JoinRequestStatus::Approved]);

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Approved);
});
