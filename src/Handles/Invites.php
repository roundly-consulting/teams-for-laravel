<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Actions\AcceptInviteAction;
use RoundlyConsulting\Teams\Actions\PruneInvitesAction;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Exceptions\InviteNotFoundException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Support\InviteModel;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::invites()` — invites across teams, addressed by model or by code.
 */
final readonly class Invites
{
    public function __construct(
        private TeamsManager $manager,
    ) {}

    /**
     * Accept an invite — the model, or its code — as `$user`, joining its team with
     * the invite's role. `$email` must match an email-targeted invite.
     *
     * @throws InviteNotFoundException when no invite has the given code
     */
    public function accept(Invite|string $invite, Model $user, ?string $email = null): Member
    {
        $invite = is_string($invite) ? $this->findOrFail($invite) : $invite;

        return $this->manager->perform(
            TeamOperation::AcceptInvite,
            AcceptInviteAction::class,
            static fn (AcceptInviteAction $action): Member => $action->execute($invite, new AcceptInviteData(
                member: $user,
                email: $email,
            )),
            ['invite' => $invite, 'member' => $user, 'email' => $email],
        );
    }

    public function find(string $code): ?Invite
    {
        /** @var Invite|null $invite */
        $invite = InviteModel::query()->where('code', $code)->first();

        return $invite;
    }

    /**
     * Force-delete invites that expired more than a month ago.
     *
     * @return int the number of invites pruned
     */
    public function prune(): int
    {
        return $this->manager->perform(
            TeamOperation::PruneInvites,
            PruneInvitesAction::class,
            static fn (PruneInvitesAction $action): int => $action->execute(),
        );
    }

    private function findOrFail(string $code): Invite
    {
        return $this->find($code) ?? throw InviteNotFoundException::forCode($code);
    }
}
