<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Permissions\PermissionsManager;
use RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects\RevokeMemberAuthorizationData;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberRevoke;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Remove a model's roles or permissions inside the team, every window of them —
 * `Permissions::for($member)->in($team)`, through the permissions bridge.
 *
 * No membership is needed: clearing the grants of someone who already left is always safe.
 */
final readonly class RevokeMemberAuthorizationAction
{
    public function __construct(
        private PermissionsManager $permissions,
    ) {}

    public function execute(Team $team, RevokeMemberAuthorizationData $data): Model
    {
        $grants = $this->permissions->for($data->member)->in($team);

        match ($data->verb) {
            MemberRevoke::RemoveRole => $grants->removeRole($data->items),
            MemberRevoke::RevokePermission => $grants->revokePermissionTo($data->items),
            MemberRevoke::ForgetAll => $grants->forgetAllAuthorization(),
        };

        return $data->member;
    }
}
