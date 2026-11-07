<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamKeys;
use RoundlyConsulting\Teams\Scoping\TeamScope;
use RoundlyConsulting\Teams\Scoping\TeamWriteGuard;

/**
 * Isolates a model's rows per team: every query sees only the current team's rows (or
 * the multi-team set's), a new row is stamped with the current team, and a write for any
 * other team throws.
 *
 * Fails closed — with no team context a query returns no rows (or throws, with
 * `teams.scoping.without_context` = `throw`). The escape hatches are deliberately loud
 * so a code review can grep them: `Model::withoutTeamScope()`, `Model::forTeam($team)`
 * and `Teams::withoutTeamScope(fn () => …)`.
 *
 * The team column is `team_id`; a model names another with a constant:
 *
 *     const TEAM_COLUMN = 'company_id';
 *
 * Add the column with `$table->teamScope()` (or `$table->teamScope('company_id')`).
 *
 * Covers Eloquent only. A raw `DB::table()` query, `Model::query()->toBase()`, a mass
 * `update()` that changes the team column, `insert()`, `upsert()`, `saveQuietly()` and
 * `Model::withoutEvents()` are outside it — see the docs.
 *
 * @phpstan-require-extends Model
 */
trait ScopedToTeam
{
    public static function bootScopedToTeam(): void
    {
        static::addGlobalScope(new TeamScope);

        static::creating(static function (Model $model): void {
            TeamWriteGuard::creating($model);
        });

        static::updating(static function (Model $model): void {
            TeamWriteGuard::updating($model);
        });

        static::deleting(static function (Model $model): void {
            TeamWriteGuard::deleting($model);
        });
    }

    /**
     * The column holding the row's team: `TEAM_COLUMN` when declared, else `team_id`.
     */
    public function getTeamColumn(): string
    {
        return TeamScope::column($this);
    }

    public function getQualifiedTeamColumn(): string
    {
        return TeamScope::qualifiedColumn($this);
    }

    /**
     * Escape hatch: this query ignores the team context entirely.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutTeamScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TeamScope::class);
    }

    /**
     * Escape hatch: this query reads the given team(s) instead of the context. Takes a
     * team, a team key, or a list/collection of either. An empty list matches nothing.
     *
     * @param  Builder<static>  $query
     * @param  Team|int|string|iterable<Team|int|string>  $teams
     * @return Builder<static>
     */
    public function scopeForTeam(Builder $query, Team|int|string|iterable $teams): Builder
    {
        $keys = TeamKeys::from($teams);

        return $query
            ->withoutGlobalScope(TeamScope::class)
            ->whereIn($this->getQualifiedTeamColumn(), $keys);
    }
}
