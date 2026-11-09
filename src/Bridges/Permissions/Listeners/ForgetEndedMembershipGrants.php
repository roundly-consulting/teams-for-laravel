<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Permissions\PermissionsManager;
use RoundlyConsulting\Teams\Bridges\Permissions\PermissionsBridge;
use RoundlyConsulting\Teams\Events\MembershipExpired;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * Table hygiene for the permissions bridge: a member's grants in a team go when the membership
 * ends for good (removed, or pruned after expiry) —
 * `Permissions::for($member)->in($team)->forgetAllAuthorization()`.
 *
 * And when a lapsed membership is revived: teams revives the same membership row ("nothing
 * of the old grant carries over"), so the grants it held before it lapsed — inert while it
 * was expired — must not come back to life with it. A brand-new membership keeps whatever
 * was granted ahead of it.
 *
 * Correctness of a check never depends on this: the union ignores the grants of a membership
 * that is not active.
 *
 * @internal
 */
final class ForgetEndedMembershipGrants
{
    public function removed(TeamMemberDeleted $event): void
    {
        $this->forget($event->member);
    }

    public function pruned(MembershipExpired $event): void
    {
        $this->forget($event->member);
    }

    public function revived(TeamMemberAdded $event): void
    {
        if (! $event->member->wasRecentlyCreated) {
            $this->forget($event->member);
        }
    }

    private function forget(Member $membership): void
    {
        if (! PermissionsBridge::enabled()) {
            return;
        }

        // Decided on the class before anything is loaded: most member types (and any stale
        // morph type that no longer resolves) cannot hold a scoped grant, and the removal,
        // prune or re-add that fired this must never fail on it.
        $class = Relation::getMorphedModel($membership->member_type) ?? $membership->member_type;

        if (! class_exists($class) || ! PermissionsBridge::holdsGrants($class)) {
            return;
        }

        $member = $membership->member()->first();

        // A soft-deleted team keeps its grants until it is force-deleted (or restored).
        $team = TeamModel::query()->withTrashed()->find($membership->team_id);

        if (! $member instanceof Model || ! $team instanceof Team) {
            return;
        }

        app(PermissionsManager::class)->for($member)->in($team)->forgetAllAuthorization();
    }
}
