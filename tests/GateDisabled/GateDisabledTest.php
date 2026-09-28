<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('does not authorize team abilities when the gate is disabled', function () {
    Teams::roles()->register('manager', 'Manager', ['manage-billing']);

    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager');

    // The gate-before hook is not registered, so even a permitted member is denied.
    expect(Gate::forUser($user)->allows('teams.manage-billing', $team))->toBeFalse();
});
