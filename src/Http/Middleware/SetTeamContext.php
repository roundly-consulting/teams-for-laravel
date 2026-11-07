<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Support\TeamsConfig;
use RoundlyConsulting\Teams\TeamsManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * `teams.context` (or `teams.context:company` for another route parameter): binds the
 * team from the route, lets through only a user who may act in it, and makes it the
 * current team.
 *
 * - The team is bound by the team model's route key and the route's binding field
 *   (`{team:slug}`), so a slug or a ULID route key works. A soft-deleted team is not found.
 * - A team that does not exist and a team the user is not an active member of (an
 *   expired membership included) get the SAME answer — 404 by default, 403 with
 *   `teams.context.deny_status` — so probing reveals nothing about other customers.
 * - The package pins this middleware before `SubstituteBindings` in the priority list,
 *   so team-scoped route models (`/teams/{team}/tickets/{ticket}`) are bound INSIDE the
 *   context instead of failing closed outside it.
 * - The context lives for the rest of the request (streamed responses and after-response
 *   jobs included); Octane and the queue worker start every request and job fresh.
 */
final readonly class SetTeamContext
{
    public function __construct(
        private TeamsManager $teams,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        $parameter = $parameter !== null && $parameter !== '' ? $parameter : TeamsConfig::contextRouteParameter();
        $route = $request->route();

        if (! $route instanceof Route || ! $route->hasParameter($parameter)) {
            throw TeamContextException::missingRouteParameter($parameter);
        }

        $team = $this->bind($route->parameter($parameter), $route->bindingFieldFor($parameter));
        $user = $request->user();

        if ($team === null || ! $user instanceof Model || ! $team->isAccessibleBy($user)) {
            abort(TeamsConfig::contextDenyStatus());
        }

        $route->setParameter($parameter, $team);

        $this->teams->setCurrent($team);

        return $next($request);
    }

    private function bind(mixed $value, ?string $field): ?Team
    {
        if ($value instanceof Team) {
            return $value->exists ? $value : null;
        }

        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $team = TeamModel::new()->resolveRouteBinding($value, $field);

        return $team instanceof Team ? $team : null;
    }
}
