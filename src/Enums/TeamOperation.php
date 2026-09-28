<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Enums;

use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Testing\TeamsFake;

/**
 * Every state-changing operation the teams API performs. Each one runs through
 * {@see TeamsManager::perform()}, which is how {@see TeamsFake} sees calls made
 * through the facade, an injected manager, the handles and the model methods alike.
 */
enum TeamOperation: string
{
    use Helpers;

    /** A team was created. */
    case CreateTeam = 'create-team';

    /** A member was added to a team (or an existing membership updated). */
    case AddMember = 'add-member';

    /** A member was removed from a team. */
    case RemoveMember = 'remove-member';

    /** A member's role was changed. */
    case ChangeRole = 'change-role';

    /** An invite was created. */
    case CreateInvite = 'create-invite';

    /** An invite was re-issued with a fresh code and expiry. */
    case ResendInvite = 'resend-invite';

    /** An invite was revoked. */
    case RevokeInvite = 'revoke-invite';

    /** An invite was accepted. */
    case AcceptInvite = 'accept-invite';

    /** A join request was opened. */
    case RequestToJoin = 'request-to-join';

    /** A join request was approved. */
    case ApproveJoinRequest = 'approve-join-request';

    /** A join request was denied. */
    case DenyJoinRequest = 'deny-join-request';

    /** A per-team role override was defined. */
    case DefineRole = 'define-role';

    /** Team ownership was transferred. */
    case TransferOwnership = 'transfer-ownership';

    /** Long-expired invites were pruned. */
    case PruneInvites = 'prune-invites';

    /** Long-expired memberships were pruned. */
    case PruneMembers = 'prune-members';

    /** Expired pending join requests were auto-declined. */
    case ExpireJoinRequests = 'expire-join-requests';

    /** MembershipExpiringSoon was dispatched for memberships lapsing soon. */
    case NotifyExpiringMembers = 'notify-expiring-members';
}
