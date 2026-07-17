<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The Eloquent models the package uses internally. Swap any of them for
    | your own subclass to extend behaviour without forking the package.
    |
    */

    'models' => [
        'team' => Team::class,
        'member' => Member::class,
        'invite' => Invite::class,
        'team_role' => TeamRole::class,
        'join_request' => JoinRequest::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic owner / member / invited_by /
    | requester / responded_by columns. Use "uuid" or "ulid" when the models
    | those columns point at use UUID/ULID primary keys, otherwise leave it as
    | "bigint". Your morph targets must share one key type; set this to match.
    | Any unrecognized value falls back to "bigint".
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('TEAMS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Roles can be defined in code (the "array" driver, fast and version
    | controlled) or stored in the database (the "database" driver, manageable
    | at runtime). The "owner" key is granted to a team's creator/owner, and
    | "admin" is the role a previous owner is demoted to on ownership transfer.
    | "default" is the role applied to new members when none is supplied (e.g.
    | when a join request is approved).
    |
    | "per_team" enables per-team role overrides: when on, a team can define its
    | own permission set for a role key that wins over the global definition.
    | When off (the default) roles resolve to the global provider with no extra
    | queries, preserving the original behaviour exactly.
    |
    | "cache" wraps the "database" provider in a cache layer that is flushed on
    | every role mutation. It is off by default.
    |
    */

    'roles' => [
        'provider' => env('TEAMS_ROLES_PROVIDER', 'array'),
        'owner' => 'owner',
        'admin' => 'admin',
        'default' => env('TEAMS_DEFAULT_ROLE', 'member'),
        'per_team' => (bool) env('TEAMS_PER_TEAM_ROLES', false),
        'cache' => [
            'enabled' => (bool) env('TEAMS_ROLES_CACHE', false),
            'store' => env('TEAMS_ROLES_CACHE_STORE'),
            'key' => env('TEAMS_ROLES_CACHE_KEY', 'teams.roles'),
            'ttl' => (int) env('TEAMS_ROLES_CACHE_TTL', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invites
    |--------------------------------------------------------------------------
    |
    | "expires_after" is a relative interval used as the default invite expiry
    | when none is supplied, and "code_length" is the length of the generated
    | random invite code. Invites default to a single use (max_uses = 1) so an
    | accepted invite is consumed and deleted; pass a higher "maxUses" to issue
    | multi-seat links, or null for unlimited.
    |
    */

    'invites' => [
        'expires_after' => env('TEAMS_INVITES_EXPIRES_AFTER', '7 days'),
        'code_length' => (int) env('TEAMS_INVITES_CODE_LENGTH', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Members
    |--------------------------------------------------------------------------
    |
    | "prune_after" is the relative interval, measured from a membership's
    | expiry, after which "teams:members:prune" force-deletes the audit row.
    | "expiring_within" is the default window (in days) for the
    | "teams:members:expiring" report and the MembershipExpiringSoon event.
    |
    */

    'members' => [
        'prune_after' => env('TEAMS_MEMBERS_PRUNE_AFTER', '30 days'),
        'expiring_within' => (int) env('TEAMS_MEMBERS_EXPIRING_WITHIN', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Join requests
    |--------------------------------------------------------------------------
    |
    | Join requests are opt-in expiring: pass an "expiresAt" to
    | Teams::requestToJoin() and "teams:join-requests:prune" auto-declines any
    | pending request whose expiry has passed (firing JoinRequestExpired). A
    | null expiry never lapses, preserving the original behaviour. "prune_after"
    | is the relative interval, measured from a request's last update, after
    | which "php artisan model:prune" force-deletes resolved (non-pending) rows.
    |
    */

    'join_requests' => [
        'prune_after' => env('TEAMS_JOIN_REQUESTS_PRUNE_AFTER', '30 days'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Approvals
    |--------------------------------------------------------------------------
    |
    | Opt-in multi-admin sign-off on join requests, routed through
    | approvals-for-laravel. When "enabled" is on, opening a request through
    | Teams::for($team)->requireApprovalFrom([...])->requestToJoinFor($user)
    | creates an ApprovalRequest and leaves the join request pending until the
    | engine resolves it; the SyncJoinRequestStatusFromApproval listener then
    | mirrors the outcome onto the join request (adding the member on approval,
    | denying on rejection). When off, that builder path falls back to the
    | native single-responder approveJoinRequest/denyJoinRequest flow.
    |
    | "rule" and "quorum" are the defaults used when the builder does not set
    | them explicitly; "rule" is an ApprovalRule value (unanimous/quorum/any/
    | weighted).
    |
    */

    'approvals' => [
        'enabled' => (bool) env('TEAMS_APPROVALS', false),
        'rule' => env('TEAMS_APPROVALS_RULE', 'unanimous'),
        'quorum' => env('TEAMS_APPROVALS_QUORUM') !== null ? (int) env('TEAMS_APPROVALS_QUORUM') : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate & permissions
    |--------------------------------------------------------------------------
    |
    | When "gate.register" is true the package registers Laravel Gate abilities
    | and Blade directives so you can authorize with $user->can('teams.<perm>',
    | $team) and @teamPermission / @teamRole. "prefix" is the ability name
    | prefix used for those gate abilities. "owner_ability" is the short name
    | that resolves to team ownership: $user->can('teams.owner', $team).
    |
    */

    'gate' => [
        'register' => (bool) env('TEAMS_REGISTER_GATE', true),
        'prefix' => env('TEAMS_GATE_PREFIX', 'teams'),
        'owner_ability' => env('TEAMS_GATE_OWNER_ABILITY', 'owner'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Optional queue connection consumed by the publishable team event
    | subscriber stub when turning team events into notifications.
    |
    */

    'notifications' => [
        'queue_connection' => env('TEAMS_NOTIFY_CONNECTION'),
    ],

];
