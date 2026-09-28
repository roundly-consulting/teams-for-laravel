<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Actions\CreateInviteAction;
use RoundlyConsulting\Teams\Actions\ResendInviteAction;
use RoundlyConsulting\Teams\Actions\RevokeInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Exceptions\InviteNotFoundException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::for($team)->invites()` — the team's invites. `resend()` and `revoke()`
 * refuse an invite that belongs to another team.
 */
final readonly class TeamInvites
{
    public function __construct(
        private TeamsManager $manager,
        private Team $team,
    ) {}

    /**
     * Create an invite. `$expiresAt` defaults to `teams.invites.expires_after` from
     * now; `$maxUses` null means unlimited.
     *
     * @param  array<string, mixed>  $meta
     */
    public function create(
        string $role,
        ?CarbonInterface $expiresAt = null,
        ?string $email = null,
        ?Model $invitedBy = null,
        array $meta = [],
        ?int $maxUses = 1,
    ): Invite {
        return $this->manager->perform(
            TeamOperation::CreateInvite,
            CreateInviteAction::class,
            fn (CreateInviteAction $action): Invite => $action->execute($this->team, new CreateInviteData(
                role: $role,
                expiresAt: $expiresAt,
                email: $email,
                invitedBy: $invitedBy,
                meta: $meta,
                maxUses: $maxUses,
            )),
            ['team' => $this->team, 'role' => $role, 'email' => $email],
        );
    }

    /**
     * Re-issue an invite in place: rotate its code and extend its expiry.
     *
     * @throws InviteNotFoundException when the invite belongs to another team
     */
    public function resend(Invite $invite): Invite
    {
        $this->guard($invite);

        return $this->manager->perform(
            TeamOperation::ResendInvite,
            ResendInviteAction::class,
            static fn (ResendInviteAction $action): Invite => $action->execute($invite),
            ['team' => $this->team, 'invite' => $invite],
        );
    }

    /**
     * @throws InviteNotFoundException when the invite belongs to another team
     */
    public function revoke(Invite $invite): bool
    {
        $this->guard($invite);

        return $this->manager->perform(
            TeamOperation::RevokeInvite,
            RevokeInviteAction::class,
            static fn (RevokeInviteAction $action): bool => $action->execute($invite),
            ['team' => $this->team, 'invite' => $invite],
        );
    }

    /**
     * The team's invites that have not expired yet.
     *
     * @return Collection<int, Invite>
     */
    public function pending(): Collection
    {
        return $this->team->invites()->pending()->get();
    }

    private function guard(Invite $invite): void
    {
        if ((string) $invite->team_id !== (string) $this->team->getKey()) {
            throw InviteNotFoundException::inTeam($invite);
        }
    }
}
