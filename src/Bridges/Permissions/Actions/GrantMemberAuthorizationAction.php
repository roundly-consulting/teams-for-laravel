<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Permissions\PermissionsManager;
use RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects\GrantMemberAuthorizationData;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberGrant;
use RoundlyConsulting\Teams\Exceptions\MemberNotFoundException;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Grant a team member roles or permissions inside the team —
 * `Permissions::for($member)->in($team)`, through the permissions bridge.
 *
 * Only for a current or booked member: a model without a membership, or with an expired one,
 * throws. Unless an end is given, the grant ends when the membership expires.
 */
final readonly class GrantMemberAuthorizationAction
{
    public function __construct(
        private PermissionsManager $permissions,
    ) {}

    /**
     * @throws MemberNotFoundException
     */
    public function execute(Team $team, GrantMemberAuthorizationData $data): Model
    {
        $membership = $team->findMember($data->member);

        if ($membership === null || $membership->isExpired()) {
            throw MemberNotFoundException::inTeam($team);
        }

        $grants = $this->permissions
            ->for($data->member)
            ->in($team)
            ->between($data->from, $data->untilGiven ? $data->until : $membership->expires_at);

        match ($data->verb) {
            MemberGrant::AssignRole => $grants->assignRole($data->items),
            MemberGrant::GivePermission => $grants->givePermissionTo($data->items),
            MemberGrant::SyncRoles => $grants->syncRoles($data->items),
            MemberGrant::SyncPermissions => $grants->syncPermissions($data->items),
        };

        return $data->member;
    }
}
