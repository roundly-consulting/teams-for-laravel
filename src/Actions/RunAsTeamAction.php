<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Closure;
use Illuminate\Foundation\Bus\PendingDispatch;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamContext;

/**
 * Run `$callback` as `$team`, then put the previous context back exactly — no context,
 * one team or a multi-team set — whether the callback returned or threw.
 *
 * A callback returning a PendingDispatch (`fn () => Job::dispatch()`) would dispatch only
 * when that object is destroyed — after the context is restored, so the job would carry
 * the WRONG team. It is dispatched here, inside the context, and null is returned.
 */
final readonly class RunAsTeamAction
{
    public function __construct(
        private TeamContext $context,
        private SetCurrentTeamAction $setCurrent,
    ) {}

    /**
     * @template TResult
     *
     * @param  Closure(Team): TResult  $callback
     * @return (TResult is PendingDispatch ? null : TResult)
     */
    public function execute(Team $team, Closure $callback): mixed
    {
        $previous = $this->context->snapshot();

        $this->setCurrent->execute($team);

        try {
            $result = $callback($team);

            if ($result instanceof PendingDispatch) {
                // The destructor dispatches — while the context is still this team's.
                unset($result);

                return null;
            }

            return $result;
        } finally {
            $this->context->restore($previous);

            TeamContextSet::dispatch($this->context->teams(), $previous->multiple);
        }
    }
}
