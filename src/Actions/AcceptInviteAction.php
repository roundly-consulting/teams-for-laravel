<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\InviteAccepted;
use RoundlyConsulting\Teams\Exceptions\InviteEmailMismatchException;
use RoundlyConsulting\Teams\Exceptions\InviteExhaustedException;
use RoundlyConsulting\Teams\Exceptions\InviteExpiredException;
use RoundlyConsulting\Teams\Exceptions\InviteNotFoundException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

final readonly class AcceptInviteAction
{
    public function __construct(
        private AddMemberAction $addMember,
    ) {}

    /**
     * Accept an invite as `$data->member`, joining its team with the invite's role.
     *
     * Atomic: the invite row is re-read under a lock (the caller's copy may be
     * stale — revoked, consumed or exhausted since it was loaded) and the seat is
     * claimed with a conditional `uses` increment, all in one transaction with the
     * member add. A single-use invite therefore admits exactly one person.
     *
     * Someone who already holds an active membership gets it back unchanged: no
     * seat is consumed and their role is never overwritten (an expired or removed
     * membership is revived through the invite instead).
     *
     * @throws InviteNotFoundException when the invite (or its team) was revoked or deleted
     * @throws InviteExpiredException when the invite has expired
     * @throws InviteExhaustedException when the invite has no seat left
     * @throws InviteEmailMismatchException when the email does not match an email-targeted invite
     */
    public function execute(Invite $invite, AcceptInviteData $data): Member
    {
        return $invite->getConnection()->transaction(function () use ($invite, $data): Member {
            $current = $this->lock($invite);

            if ($current->isExpired()) {
                throw InviteExpiredException::for($current);
            }

            if ($current->isExhausted()) {
                throw InviteExhaustedException::for($current);
            }

            if ($current->trashed()) {
                throw InviteNotFoundException::unavailable($current);
            }

            if ($current->email !== null && ! $this->sameEmail($current->email, $data->email)) {
                throw InviteEmailMismatchException::for($current);
            }

            $team = $current->team;

            if (! $team instanceof Team) {
                throw InviteNotFoundException::teamMissing($current);
            }

            $membership = $team->members()
                ->whereMorphedTo('member', $data->member)
                ->lockForUpdate()
                ->first();

            if ($membership instanceof Member && ! $membership->isExpired()) {
                return $membership;
            }

            $this->claimSeat($current);

            $member = $this->addMember->execute($team, new AddMemberData(
                member: $data->member,
                role: $current->role,
                acceptedInviteId: (int) $current->getKey(),
            ));

            $current->refresh();

            if ($current->isExhausted()) {
                $current->delete();
            }

            // Keep the caller's copy in step with the row it handed us.
            $invite->setRawAttributes($current->getAttributes(), true);

            InviteAccepted::dispatch($invite, $member);

            return $member;
        });
    }

    /**
     * The invite's current row, locked for the rest of the transaction. Trashed
     * rows are included so a consumed link reports "exhausted", not "not found".
     */
    private function lock(Invite $invite): Invite
    {
        /** @var Invite|null $current */
        $current = $invite->newQuery()
            ->withTrashed()
            ->whereKey($invite->getKey())
            ->lockForUpdate()
            ->first();

        return $current ?? throw InviteNotFoundException::unavailable($invite);
    }

    /**
     * Email addresses compare case-insensitively: `Jane@Acme.test` is `jane@acme.test`.
     */
    private function sameEmail(string $invited, ?string $given): bool
    {
        return $given !== null && Str::lower(trim($invited)) === Str::lower(trim($given));
    }

    /**
     * Take one seat with a conditional UPDATE: it only succeeds while a seat is
     * left, so two acceptances can never both take the last one.
     */
    private function claimSeat(Invite $invite): void
    {
        $claimed = $invite->newQuery()
            ->whereKey($invite->getKey())
            ->where(function (Builder $query): void {
                $query->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses');
            })
            ->increment('uses');

        if ($claimed === 0) {
            throw InviteExhaustedException::for($invite);
        }
    }
}
