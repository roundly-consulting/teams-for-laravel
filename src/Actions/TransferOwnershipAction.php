<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\TeamOwnershipTransferred;
use RoundlyConsulting\Teams\Models\Team;

final readonly class TransferOwnershipAction
{
    public function __construct(
        private AddMemberAction $addMember,
    ) {}

    /**
     * Transfer team ownership to a new owner. The new owner is ensured to be a
     * member with the configured owner role; the previous owner, if any, is
     * demoted to a regular member with the configured admin role.
     *
     * All-or-nothing: one transaction, and the new owner is seated first, so a
     * refusal (the seat cap) leaves the owner, the roster and every role as they
     * were.
     */
    public function execute(Team $team, Model $newOwner): Team
    {
        /** @var string $ownerRole */
        $ownerRole = config('teams.roles.owner', 'owner');

        /** @var string $adminRole */
        $adminRole = config('teams.roles.admin', 'admin');

        return $team->getConnection()->transaction(function () use ($team, $newOwner, $ownerRole, $adminRole): Team {
            $previousOwner = $team->owner;

            $this->addMember->execute($team, new AddMemberData(
                member: $newOwner,
                role: $ownerRole,
            ));

            if ($previousOwner !== null && ! $previousOwner->is($newOwner)) {
                $this->addMember->execute($team, new AddMemberData(
                    member: $previousOwner,
                    role: $adminRole,
                ));
            }

            $team->update([
                'owner_type' => $newOwner->getMorphClass(),
                'owner_id' => $newOwner->getKey(),
            ]);

            TeamOwnershipTransferred::dispatch($team, $previousOwner, $newOwner);

            return $team;
        });
    }
}
