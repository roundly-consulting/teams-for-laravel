<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Invite;

it('scopes to pending invites', function () {
    $pending = Invite::factory()->create();
    $expired = Invite::factory()->expired()->create();

    $ids = Invite::query()->pending()->pluck('id');

    expect($ids)->toContain($pending->id)->not->toContain($expired->id);
});

it('scopes by email', function () {
    $match = Invite::factory()->forEmail('jane@acme.test')->create();
    Invite::factory()->forEmail('other@acme.test')->create();

    $ids = Invite::query()->forEmail('jane@acme.test')->pluck('id');

    expect($ids)->toContain($match->id)->toHaveCount(1);
});

it('uses the code as its route key', function () {
    expect((new Invite)->getRouteKeyName())->toBe('code');
});

it('revokes itself through the model helper', function () {
    $invite = Invite::factory()->create();

    expect($invite->revoke())->toBeTrue();
    $this->assertSoftDeleted($invite);
});
