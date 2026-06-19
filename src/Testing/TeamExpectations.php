<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Registers Pest expectation matchers for team assertions. Host applications
 * call {@see TeamExpectations::register()} from their tests/Pest.php.
 *
 * The matchers are only defined when Pest's expectation API is available, so
 * this file never pulls Pest into the package's runtime and static analysis
 * stays clean.
 */
final class TeamExpectations
{
    public static function register(): void
    {
        if (! function_exists('expect')) {
            return;
        }

        expect()->extend('toBeMemberOf', function (Team $team): mixed {
            /** @var Model $user */
            $user = $this->value;

            expect($team->hasMember($user))->toBeTrue();

            return $this;
        });

        expect()->extend('withRole', function (Team $team, string $role): mixed {
            /** @var Model $user */
            $user = $this->value;

            expect($team->memberHasRole($user, $role))->toBeTrue();

            return $this;
        });

        expect()->extend('toHaveTeamPermission', function (Team $team, string $permission): mixed {
            /** @var Model $user */
            $user = $this->value;

            expect($team->memberHasPermission($user, $permission))->toBeTrue();

            return $this;
        });
    }
}
