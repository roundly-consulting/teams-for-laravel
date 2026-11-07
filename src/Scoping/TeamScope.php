<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Support\TeamsConfig;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

/**
 * The global scope behind {@see ScopedToTeam}: a scoped query only ever sees the current
 * team's rows (or the multi-team set's).
 *
 * It FAILS CLOSED. Without a context it constrains to no rows at all — or throws, with
 * `teams.scoping.without_context` = `throw` — and never degrades to an unscoped query:
 * a forgotten middleware must surface as an empty page, not as another customer's data.
 * The only ways around it are explicit and greppable: `Model::withoutTeamScope()`,
 * `Model::forTeam($team)` and `Teams::withoutTeamScope(fn () => …)`.
 *
 * @internal
 *
 * @implements Scope<Model>
 */
final class TeamScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TeamContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $column = self::qualifiedColumn($model);
        $keys = $context->keys();

        if ($keys === null) {
            if (TeamsConfig::throwWithoutContext()) {
                throw TeamContextException::missing();
            }

            // An empty IN compiles to `0 = 1` on every grammar: no rows, portable.
            $builder->whereIn($column, []);

            return;
        }

        if (count($keys) === 1) {
            $builder->where($column, '=', $keys[0]);

            return;
        }

        $builder->whereIn($column, $keys);
    }

    public static function qualifiedColumn(Model $model): string
    {
        return $model->qualifyColumn(self::column($model));
    }

    /**
     * The model's team column: `TEAM_COLUMN` when the model declares the constant, else
     * `team_id` — the same per-model convention Laravel uses for `DELETED_AT`.
     */
    public static function column(Model $model): string
    {
        $constant = $model::class.'::TEAM_COLUMN';

        if (defined($constant)) {
            $column = constant($constant);

            if (is_string($column) && $column !== '') {
                return $column;
            }
        }

        return 'team_id';
    }
}
