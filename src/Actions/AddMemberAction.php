<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Options\MaxSeats;
use RoundlyConsulting\Teams\Support\TeamsConfig;

final readonly class AddMemberAction
{
    /**
     * Add a member to a team. A team holds one membership row per member (a unique
     * index, soft-deleted rows included):
     *
     *  - an **active** member is idempotent — the existing membership is returned,
     *    its role updated when the requested role differs and its expiry when one
     *    is supplied;
     *  - an **expired** or **removed** member is revived in place as a new
     *    membership (role, meta, expiry and accepted invite from this add);
     *  - anyone else gets a new row.
     *
     * A start date (`teams.members.start_dates`) books a membership that begins later; it
     * takes its seat from the moment it is booked. Without the option a start date throws
     * rather than being ignored — ignored, the member would be active at once.
     *
     * Runs in one transaction that first locks the team row, so concurrent adds to
     * a team serialise and the seat cap holds. Every membership read is a locking
     * read; a duplicate insert that still slips through is resolved to the row
     * that won.
     */
    public function execute(Team $team, AddMemberData $data): Member
    {
        $this->guardStartDate($data);

        return $team->getConnection()->transaction(function () use ($team, $data): Member {
            $team->newQueryWithoutScopes()->whereKey($team->getKey())->lockForUpdate()->first();

            $existing = $this->find($team, $data->member);

            if ($existing !== null) {
                return $this->resolve($team, $existing, $data);
            }

            $this->guardSeatLimit($team);

            try {
                // A savepoint, so a lost insert race leaves the outer transaction usable.
                $created = $team->getConnection()->transaction(fn (): Member => $this->create($team, $data));
            } catch (UniqueConstraintViolationException $exception) {
                return $this->resolve($team, $this->find($team, $data->member) ?? throw $exception, $data);
            }

            TeamMemberAdded::dispatch($created);

            return $created;
        });
    }

    private function resolve(Team $team, Member $existing, AddMemberData $data): Member
    {
        if ($existing->trashed() || $existing->isExpired()) {
            $this->guardSeatLimit($team);

            return $this->revive($existing, $data);
        }

        return $this->update($existing, $data);
    }

    private function update(Member $existing, AddMemberData $data): Member
    {
        // The window is checked against the expiry the membership will have, which an
        // idempotent re-add without one keeps.
        $expiresAt = $data->expiresAt ?? $existing->expires_at;

        if ($data->startsAt !== null && $expiresAt !== null && $data->startsAt->greaterThanOrEqualTo($expiresAt)) {
            throw TeamsException::invalidMembershipWindow();
        }

        // Only overwrite the expiry when one is explicitly supplied, so an
        // idempotent re-add does not silently clear an existing expiry.
        if ($data->expiresAt !== null) {
            $existing->update(['expires_at' => $data->expiresAt]);
        }

        if ($data->startsAt !== null) {
            $existing->update(['starts_at' => $data->startsAt]);
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

    /**
     * A removed or lapsed membership comes back as a new one: nothing of the old
     * grant (role, expiry, meta, invite link) carries over.
     */
    private function revive(Member $existing, AddMemberData $data): Member
    {
        $existing->fill([
            'role' => $data->role,
            'meta' => new Collection($data->meta),
            'expires_at' => $data->expiresAt,
            'accepted_invite_id' => $data->acceptedInviteId,
            ...$this->startColumn($data),
        ]);

        if ($existing->trashed()) {
            $existing->restore();
        } else {
            $existing->save();
        }

        TeamMemberAdded::dispatch($existing);

        return $existing;
    }

    private function create(Team $team, AddMemberData $data): Member
    {
        /** @var Member $created */
        $created = $team->members()->create([
            'member_type' => $data->member->getMorphClass(),
            'member_id' => $data->member->getKey(),
            'role' => $data->role,
            'meta' => new Collection($data->meta),
            'expires_at' => $data->expiresAt,
            'accepted_invite_id' => $data->acceptedInviteId,
            ...$this->startColumn($data),
        ]);

        return $created;
    }

    /**
     * A start date is honoured only with `teams.members.start_dates` on, and must come
     * before the expiry — a membership that ends before it begins is never active.
     */
    private function guardStartDate(AddMemberData $data): void
    {
        if ($data->startsAt === null) {
            return;
        }

        if (! TeamsConfig::memberStartDates()) {
            throw TeamsException::startDatesDisabled();
        }

        if ($data->expiresAt !== null && $data->startsAt->greaterThanOrEqualTo($data->expiresAt)) {
            throw TeamsException::invalidMembershipWindow();
        }
    }

    /**
     * The `starts_at` column — written only with `teams.members.start_dates` on, so a host
     * that has not published that migration never touches it. A revived membership
     * without a start date starts at once.
     *
     * @return array<string, CarbonInterface|null>
     */
    private function startColumn(AddMemberData $data): array
    {
        return TeamsConfig::memberStartDates() ? ['starts_at' => $data->startsAt] : [];
    }

    private function find(Team $team, Model $member): ?Member
    {
        /** @var Member|null $found */
        $found = $team->members()
            ->withTrashed()
            ->whereMorphedTo('member', $member)
            ->lockForUpdate()
            ->first();

        return $found;
    }

    /**
     * Enforce the team's MaxSeats option when one is set. Only new seats (and
     * revived ones) are checked; an idempotent re-add of an active member never
     * trips the cap. The active rows are read with a lock — a current read, so a
     * seat taken by a transaction that committed while this one waited on the team
     * lock is always counted.
     */
    private function guardSeatLimit(Team $team): void
    {
        /** @var int|null $maxSeats */
        $maxSeats = Options::get(MaxSeats::class, $team);

        if ($maxSeats === null) {
            return;
        }

        // Ids, not COUNT(*): PostgreSQL refuses FOR UPDATE on an aggregate. Unexpired, not
        // active: a membership booked to start later already holds its seat.
        $taken = count($team->members()->unexpired()->lockForUpdate()->pluck('id')->all());

        if ($taken >= $maxSeats) {
            throw TeamsException::maxSeatsReached($maxSeats);
        }
    }
}
