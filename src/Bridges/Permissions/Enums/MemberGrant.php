<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The additive or authoritative writes of `Teams::for($team)->members()->grant($member)`.
 */
enum MemberGrant: string
{
    use Helpers;

    /** Add roles in the team (additive). */
    case AssignRole = 'assign-role';

    /** Add direct permissions in the team (additive). */
    case GivePermission = 'give-permission';

    /** Make the given roles the member's complete role set in the team. */
    case SyncRoles = 'sync-roles';

    /** Make the given permissions the member's complete direct-permission set in the team. */
    case SyncPermissions = 'sync-permissions';
}
