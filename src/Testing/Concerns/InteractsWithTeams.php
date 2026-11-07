<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Testing\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * For a host's test case: `$this->actingInTeam($user, $team)` authenticates the user and
 * makes the team current, as the `teams.context` middleware would. Membership is not
 * checked — add the user to the team first if the code under test checks it.
 *
 *     uses(InteractsWithTeams::class)->in('Feature');
 *
 * Needs a Laravel (or Testbench) test case — it calls `actingAs()`.
 */
trait InteractsWithTeams
{
    public function actingInTeam(Authenticatable $user, Team $team, ?string $guard = null): static
    {
        $this->actingAs($user, $guard);

        app(TeamsManager::class)->setCurrent($team);

        return $this;
    }
}
