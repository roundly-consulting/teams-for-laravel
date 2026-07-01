<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Options\MaxSeats;

final class AddMemberAction
{
    /**
     * Add a member to a team. The operation is idempotent: adding an existing
     * member does not throw — the existing membership is returned, with its role
     * updated when the requested role differs.
     */
    public function execute(Team $team, AddMemberData $data): Member
    {
        $existing = $team->findMember($data->member);

        if ($existing !== null) {
            // Only overwrite the expiry when one is explicitly supplied, so an
            // idempotent re-add does not silently clear an existing expiry.
            if ($data->expiresAt !== null) {
                $existing->update(['expires_at' => $data->expiresAt]);
            }

            // Preserve the originally accepted invite: only stamp it when the
            // membership has none yet, so a re-add never rewrites the seat link.
            if ($data->acceptedInviteId !== null && $existing->accepted_invite_id === null) {
                $existing->update(['accepted_invite_id' => $data->acceptedInviteId]);
            }

            if ($existing->role !== $data->role) {
                $previousRole = $existing->role;
                $existing->update(['role' => $data->role]);

                TeamMemberRoleChanged::dispatch($existing, $previousRole);
            }

            return $existing;
        }

        $this->guardSeatLimit($team);

        /** @var Member $created */
        $created = $team->members()->create([
            'member_type' => $data->member->getMorphClass(),
            'member_id' => $data->member->getKey(),
            'role' => $data->role,
            'meta' => new Collection($data->meta),
            'expires_at' => $data->expiresAt,
            'accepted_invite_id' => $data->acceptedInviteId,
        ]);

        TeamMemberAdded::dispatch($created);

        return $created;
    }

    /**
     * Enforce the team's MaxSeats option when one is set. Only new seats are
     * checked; an idempotent re-add of an existing member never trips the cap.
     */
    private function guardSeatLimit(Team $team): void
    {
        /** @var int|null $maxSeats */
        $maxSeats = Options::get(MaxSeats::class, $team);

        if ($maxSeats === null) {
            return;
        }

        if ($team->members()->active()->count() >= $maxSeats) {
            throw TeamsException::maxSeatsReached($maxSeats);
        }
    }
}
