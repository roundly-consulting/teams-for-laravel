<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Models\Team;

/**
 * The current team context changed: set by `Teams::setCurrent()`, `setCurrentMany()`,
 * the `teams.context` middleware, entered or restored by `Teams::runAs()`, or restored
 * from a queued job's payload (`$restored`).
 *
 * `$teams` is empty when the context was cleared. Listen to it for per-team setup that
 * is not data — URL defaults, a per-team config value, a log channel.
 */
final class TeamContextSet
{
    use Dispatchable;

    /**
     * @param  Collection<int, Team>  $teams
     */
    public function __construct(
        public Collection $teams,
        public bool $multiple = false,
        public bool $restored = false,
    ) {}

    /**
     * The single current team, or null when cleared or in multi-team mode.
     */
    public function team(): ?Team
    {
        return $this->multiple ? null : $this->teams->first();
    }
}
