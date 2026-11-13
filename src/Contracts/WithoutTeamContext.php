<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Contracts;

/**
 * A queued job that takes its team as an argument and must not inherit the dispatcher's
 * team context.
 *
 * Its payload carries no team context, so nothing is restored, nothing is re-checked
 * (`teams.context.recheck_queued_jobs`) and it runs with no current team — even when it is
 * dispatched by a member who is leaving, or on a worker that just ran another team's job.
 * Every scoped query inside it must name its team: `Teams::runAs($this->team, …)`,
 * `Model::forTeam($this->team)` or an explicit `withoutTeamScope()`. A `ScopedToTeam`
 * read without one returns nothing (or throws, per `teams.scoping.without_context`).
 *
 * Honoured on jobs, queued event listeners, queued notifications and queued mailables. A
 * job chained after it continues from its (empty) context, so it runs without a team too.
 *
 * For a one-off dispatch of a job you do not own, use `Teams::withoutContext()` instead.
 */
interface WithoutTeamContext {}
