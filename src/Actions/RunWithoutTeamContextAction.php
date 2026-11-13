<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Closure;
use Illuminate\Foundation\Bus\PendingDispatch;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Scoping\TeamContext;

/**
 * Run `$callback` with the team context suspended, then put it back exactly — no context,
 * one team or a multi-team set, with its acting member — whether the callback returned or
 * threw. Jobs dispatched inside carry no team context, so they are neither restored nor
 * re-checked (`teams.context.recheck_queued_jobs`) when they start.
 *
 * Scoped models see no context inside the callback either: reads return nothing (or throw,
 * per `teams.scoping.without_context`) and writes throw. It is meant for dispatching.
 *
 * A callback returning a PendingDispatch (`fn () => Job::dispatch()`) would dispatch only
 * when that object is destroyed — after the context is back, so the job would carry it
 * after all. It is dispatched here, inside the callback's empty context, and null is
 * returned. Nesting is safe: each call restores what it found.
 */
final readonly class RunWithoutTeamContextAction
{
    public function __construct(
        private TeamContext $context,
    ) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return (TResult is PendingDispatch ? null : TResult)
     */
    public function execute(Closure $callback): mixed
    {
        $previous = $this->context->snapshot();
        $suspended = $previous->keys !== null;

        if ($suspended) {
            $this->context->clear();

            TeamContextSet::dispatch($this->context->teams());
        }

        try {
            $result = $callback();

            if ($result instanceof PendingDispatch) {
                // The destructor dispatches — while the context is still suspended.
                unset($result);

                return null;
            }

            return $result;
        } finally {
            $this->context->restore($previous);

            if ($suspended) {
                TeamContextSet::dispatch($this->context->teams(), $previous->multiple);
            }
        }
    }
}
