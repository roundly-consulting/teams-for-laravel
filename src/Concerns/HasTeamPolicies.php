<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Helpers for hand-written team-scoped policies, keeping policy methods terse.
 */
trait HasTeamPolicies
{
    protected function owns(Model $user, Team $team): bool
    {
        return $team->isOwnedBy($user);
    }

    protected function allows(Model $user, Team $team, string $permission): bool
    {
        return $team->memberHasPermission($user, $permission);
    }
}
