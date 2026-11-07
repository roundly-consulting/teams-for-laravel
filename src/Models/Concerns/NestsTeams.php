<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamHierarchy;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * Nested teams: a team's parent, children, root, ancestors and descendants.
 *
 * A trait, booted by name, so the hierarchy guard also runs on a host's
 * `teams.models.team` subclass that overrides `booted()` without calling the parent.
 *
 * With `teams.nesting.enabled` off — the default — nothing here touches the
 * `parent_id` column: ancestors and descendants are empty and the root is the team
 * itself, so a host that has not published the nesting migration is unaffected. Only
 * setting a parent throws.
 *
 * @phpstan-require-extends Team
 */
trait NestsTeams
{
    public static function bootNestsTeams(): void
    {
        static::saving(static function (Team $team): void {
            TeamHierarchy::guard($team);
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TeamModel::class(), TeamHierarchy::PARENT_COLUMN);
    }

    /** @return HasMany<Team, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(TeamModel::class(), TeamHierarchy::PARENT_COLUMN);
    }

    /**
     * The teams above this one, nearest first.
     *
     * @return Collection<int, Team>
     */
    public function ancestors(): Collection
    {
        return TeamHierarchy::ancestors($this);
    }

    /**
     * Every team below this one, level by level.
     *
     * @return Collection<int, Team>
     */
    public function descendants(): Collection
    {
        return TeamHierarchy::descendants($this);
    }

    /**
     * The topmost team above this one, or this team when it has no parent.
     */
    public function root(): Team
    {
        return $this->ancestors()->last() ?? $this;
    }

    /**
     * How far below its root this team sits; a root is 0.
     */
    public function depth(): int
    {
        return $this->ancestors()->count();
    }
}
