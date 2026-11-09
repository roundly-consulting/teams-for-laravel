<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The removals of `Teams::for($team)->members()->grant($member)`. Each clears every window.
 */
enum MemberRevoke: string
{
    use Helpers;

    /** Remove roles in the team. */
    case RemoveRole = 'remove-role';

    /** Revoke direct permissions in the team. */
    case RevokePermission = 'revoke-permission';

    /** Remove every role and direct permission the member holds in the team. */
    case ForgetAll = 'forget-all';
}
