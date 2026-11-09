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

/**
 * The CHECKED way to make `$team` the current team: `$user` (default: the authenticated
 * user) must be allowed to act in it — an active membership, or with
 * `teams.nesting.inherit_roles_down` one on a team above. Anything else throws and leaves
 * the context untouched (fail closed).
 *
 * The context also records `$user` as its acting member, so jobs dispatched from here can
 * check that member again when they start (`teams.context.recheck_queued_jobs`).
 */
final readonly class SwitchTeamAction
{
    public function __construct(
        private TeamContext $context,
        private AuthFactory $auth,
    ) {}

    public function execute(Team $team, ?Model $user = null): void
    {
        TeamKeys::from($team);

        $user ??= $this->authenticated();

        if ($user === null) {
            throw TeamContextException::noUserToSwitch();
        }

        if (! $team->isAccessibleBy($user)) {
            throw TeamContextException::notAccessible($team);
        }

        $this->context->set(new Collection([$team]), multiple: false, actor: $user);

        TeamContextSet::dispatch(new Collection([$team]));
    }

    private function authenticated(): ?Model
    {
        $user = $this->auth->guard()->user();

        return $user instanceof Model ? $user : null;
    }
}
