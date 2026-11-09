<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\Scoping\TeamKeys;
use RoundlyConsulting\Teams\Support\MemberModel;

/**
 * Multi-team mode, for "all my teams" views: scoped models read the rows of every team
 * given, and a write must name one of them explicitly.
 *
 * Every team must be one `$user` (default: the authenticated user) may act in — an active
 * membership, or with `teams.nesting.inherit_roles_down` one on a team above. One team
 * outside that throws and leaves the context untouched: the set is all or nothing.
 *
 * The context records `$user` as its acting member, so jobs dispatched from here can check
 * that member again when they start (`teams.context.recheck_queued_jobs`).
 */
final readonly class SetCurrentTeamsAction
{
    public function __construct(
        private TeamContext $context,
        private AuthFactory $auth,
    ) {}

    /**
     * @param  iterable<Team>  $teams
     */
    public function execute(iterable $teams, ?Model $user = null): void
    {
        $user ??= $this->authenticated();

        if ($user === null) {
            throw TeamContextException::noUser();
        }

        /** @var Collection<int, Team> $unique */
        $unique = (new Collection($teams))
            ->each(static function (Team $team): void {
                TeamKeys::from($team);
            })
            ->unique(static fn (Team $team): string => (string) $team->getKey())
            ->values();

        $direct = $unique->isEmpty() ? new Collection : MemberModel::query()
            ->active()
            ->whereIn('team_id', $unique->map(static fn (Team $team): mixed => $team->getKey())->all())
            ->whereMorphedTo('member', $user)
            ->pluck('team_id')
            ->map(static fn (mixed $key): string => is_scalar($key) ? (string) $key : '')
            ->flip();

        foreach ($unique as $team) {
            if (! $direct->has((string) $team->getKey()) && ! $team->isAccessibleBy($user)) {
                throw TeamContextException::notAccessible($team);
            }
        }

        $this->context->set($unique, multiple: true, actor: $user);

        TeamContextSet::dispatch($unique, true);
    }

    private function authenticated(): ?Model
    {
        $user = $this->auth->guard()->user();

        return $user instanceof Model ? $user : null;
    }
}
