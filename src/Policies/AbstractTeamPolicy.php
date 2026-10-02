<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Policies;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Concerns\HasTeamPolicies;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Base team policy. Owners are granted every ability before per-method checks;
 * subclasses authorize each ability through a team permission. Generate a
 * starting subclass with `php artisan teams:policy`.
 */
abstract class AbstractTeamPolicy
{
    use HasTeamPolicies;

    /**
     * Owners pass every ability on their team; everything else falls through to
     * the per-method check. Class-level abilities (`create`, `viewAny`) arrive
     * with a class-string or no argument at all, and simply fall through.
     */
    public function before(Model $user, string $ability, mixed $team = null): ?bool
    {
        return $team instanceof Team && $this->owns($user, $team) ? true : null;
    }
}
