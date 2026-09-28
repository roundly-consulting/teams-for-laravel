<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\InviteAccepted;
use RoundlyConsulting\Teams\Exceptions\InviteEmailMismatchException;
use RoundlyConsulting\Teams\Exceptions\InviteExhaustedException;
use RoundlyConsulting\Teams\Exceptions\InviteExpiredException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

final readonly class AcceptInviteAction
{
    public function __construct(
        private AddMemberAction $addMember,
    ) {}

    public function execute(Invite $invite, AcceptInviteData $data): Member
    {
        if ($invite->isExpired()) {
            throw InviteExpiredException::for($invite);
        }

        if ($invite->isExhausted()) {
            throw InviteExhaustedException::for($invite);
        }

        if ($invite->email !== null && $invite->email !== $data->email) {
            throw InviteEmailMismatchException::for($invite);
        }

        /** @var Team $team */
        $team = $invite->team;

        $member = $this->addMember->execute($team, new AddMemberData(
            member: $data->member,
            role: $invite->role,
            acceptedInviteId: (int) $invite->getKey(),
        ));

        $invite->increment('uses');

        if ($invite->isExhausted()) {
            $invite->delete();
        }

        InviteAccepted::dispatch($invite, $member);

        return $member;
    }
}
