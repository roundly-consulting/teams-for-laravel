<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

/**
 * The write half of {@see ScopedToTeam}, hung on the model's `creating`, `updating` and
 * `deleting` events: a row is only ever written for a team inside the current context.
 *
 * - creating, one team in context: a blank team column is filled with it; an explicit
 *   one must be it.
 * - creating, multi-team mode: the team must be given explicitly and be in the set.
 * - updating / deleting: the row's stored team must be in context, and so must a new
 *   one when the team column changes — a row cannot be moved out of (or into) a team
 *   the context does not hold.
 * - no context: every write throws.
 *
 * @internal
 */
final class TeamWriteGuard
{
    public static function creating(Model $model): void
    {
        $context = app(TeamContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $keys = $context->keys() ?? throw TeamContextException::missing();
        $column = TeamScope::column($model);
        $team = $model->getAttribute($column);

        if (self::isBlank($team)) {
            if ($context->isMultiple() || $keys === []) {
                throw TeamContextException::ambiguous();
            }

            $model->setAttribute($column, $keys[0]);

            return;
        }

        self::assertAllowed($context, $team);
    }

    public static function updating(Model $model): void
    {
        $context = app(TeamContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $column = TeamScope::column($model);

        self::assertAllowed($context, $model->getRawOriginal($column));

        if ($model->isDirty($column)) {
            self::assertAllowed($context, $model->getAttribute($column));
        }
    }

    public static function deleting(Model $model): void
    {
        $context = app(TeamContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $column = TeamScope::column($model);

        self::assertAllowed($context, $model->getRawOriginal($column, $model->getAttribute($column)));
    }

    private static function assertAllowed(TeamContext $context, mixed $team): void
    {
        if (! $context->has()) {
            throw TeamContextException::missing();
        }

        if (! is_int($team) && ! is_string($team)) {
            throw TeamContextException::outsideContext('?');
        }

        if (! $context->allows($team)) {
            throw TeamContextException::outsideContext($team);
        }
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
