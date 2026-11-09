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
    | Any other value throws an InvalidConfigurationException when the
    | migrations run.
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
    | "provider" must be "array" or "database", the role keys strings and
    | "ttl" 1-31536000 seconds; anything else throws an
    | InvalidConfigurationException rather than falling back to a default.
    | A blank value (a bare "TEAMS_ROLES_PROVIDER=") is not set and takes
    | the default; that holds for every key in this file.
    |
    */

    'roles' => [
        'provider' => env('TEAMS_ROLES_PROVIDER', 'array'),
        'owner' => 'owner',
        'admin' => 'admin',
        'default' => env('TEAMS_DEFAULT_ROLE', 'member'),
        'per_team' => env('TEAMS_PER_TEAM_ROLES', false),
        'cache' => [
            'enabled' => env('TEAMS_ROLES_CACHE', false),
            'store' => env('TEAMS_ROLES_CACHE_STORE'),
            'key' => env('TEAMS_ROLES_CACHE_KEY', 'teams.roles'),
            'ttl' => env('TEAMS_ROLES_CACHE_TTL', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invites
    |--------------------------------------------------------------------------
    |
    | "expires_after" is a positive relative interval (e.g. "7 days") used as
    | the default invite expiry when none is supplied, and "code_length" is the
    | length (8-128) of the generated random invite code: URL-safe
    | alphanumerics, so even the longest code sits comfortably in a link.
    | Invites default to a single use (max_uses = 1) so an accepted invite is
    | consumed and deleted; pass a higher "maxUses" to issue multi-seat links,
    | or null for unlimited.
    |
    */

    'invites' => [
        'expires_after' => env('TEAMS_INVITES_EXPIRES_AFTER', '7 days'),
        'code_length' => env('TEAMS_INVITES_CODE_LENGTH', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Members
    |--------------------------------------------------------------------------
    |
    | "prune_after" is the relative interval, measured from a membership's
    | expiry, after which "teams:members:prune" (or "php artisan model:prune")
    | force-deletes the audit row, firing MembershipExpired for each.
    | "expiring_within" is the default window (in days) for the
    | "teams:members:expiring" report and the MembershipExpiringSoon event.
    |
    | "start_dates" lets a membership start in the future: pass "startsAt"
    | to Teams::for($team)->members()->add(...) and the member has no access,
    | role or permission until then. It needs the
    | "add_starts_at_to_team_members" migration (re-run vendor:publish
    | --tag=teams-migrations, then migrate). Off by default: nothing reads or
    | writes the column, and a "startsAt" throws rather than being ignored.
    |
    */

    'members' => [
        'prune_after' => env('TEAMS_MEMBERS_PRUNE_AFTER', '30 days'),
        'expiring_within' => env('TEAMS_MEMBERS_EXPIRING_WITHIN', 7),
        'start_dates' => env('TEAMS_MEMBERS_START_DATES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Join requests
    |--------------------------------------------------------------------------
    |
    | Join requests are opt-in expiring: pass an "expiresAt" to
    | Teams::for($team)->joinRequests()->open($user, expiresAt: ...) and
    | "teams:join-requests:prune" auto-declines any pending request whose
    | expiry has passed (firing JoinRequestExpired). A
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
    | Teams::for($team)->joinRequests()->requireApprovalFrom([...])->open($user)
    | creates an ApprovalRequest and leaves the join request pending until the
    | engine resolves it; the SyncJoinRequestStatusFromApproval listener then
    | mirrors the outcome onto the join request (adding the member on approval,
    | denying on rejection). When off, that path falls back to the native
    | single-responder joinRequests()->approve()/deny() flow.
    |
    | "rule" and "quorum" are the defaults used when the handle does not set
    | them explicitly; "rule" is an ApprovalRule value (unanimous/quorum/any/
    | weighted) and any other value throws an InvalidConfigurationException.
    |
    */

    'approvals' => [
        'enabled' => env('TEAMS_APPROVALS', false),
        'rule' => env('TEAMS_APPROVALS_RULE', 'unanimous'),
        'quorum' => env('TEAMS_APPROVALS_QUORUM'),
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
        'register' => env('TEAMS_REGISTER_GATE', true),
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

    /*
    |--------------------------------------------------------------------------
    | Current team context
    |--------------------------------------------------------------------------
    |
    | The "teams.context" route middleware reads the team from the route
    | parameter named here (override per route: "teams.context:company"),
    | binds it by the team model's route key (so a slug or ULID route key
    | works), checks the user holds an active membership and sets the
    | current team. "deny_status" is what a non-member gets: 404 (the
    | default — another team's existence is not revealed) or 403.
    |
    | A context set through a membership check (the middleware,
    | Teams::switchTo(), Teams::setCurrentMany()) remembers the member it
    | was checked for, and queued jobs dispatched from it carry that member.
    | With "recheck_queued_jobs" on, such a job checks the membership again
    | before it runs and fails (TeamContextException, no retry) when the
    | member was removed, expired or deleted in the meantime. Contexts set
    | with the unchecked Teams::setCurrent() carry no member and are not
    | re-checked.
    |
    */

    'context' => [
        'route_parameter' => env('TEAMS_CONTEXT_ROUTE_PARAMETER', 'team'),
        'deny_status' => env('TEAMS_CONTEXT_DENY_STATUS', 404),
        'recheck_queued_jobs' => env('TEAMS_CONTEXT_RECHECK_QUEUED_JOBS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Team-scoped models
    |--------------------------------------------------------------------------
    |
    | Models using the ScopedToTeam trait only ever read and write the rows of
    | the current team(s). "without_context" decides what a scoped query does
    | when no team context is set: "empty" (return no rows) or "throw" (a
    | TeamContextException). It never returns every team's rows.
    |
    | "models" lists scoped models explicitly and "discover" lists the
    | directories `php artisan teams:doctor` scans for them; the doctor fails
    | when a scoped table lacks its team column or an index on it.
    |
    */

    'scoping' => [
        'without_context' => env('TEAMS_SCOPING_WITHOUT_CONTEXT', 'empty'),
        'models' => [],
        'discover' => [app_path('Models')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nested teams
    |--------------------------------------------------------------------------
    |
    | Off by default. When on, a team may have a parent (company → department
    | → team). "max_depth" is how far below its root a team may sit: the root
    | has depth 0, so 2 allows company → department → team. Cycles are always
    | rejected. Membership stays per team; with "inherit_roles_down" on, a
    | role held on a team also applies on every team below it (role and
    | permission checks, the Gate, Blade directives and the context
    | middleware honour it).
    |
    */

    'nesting' => [
        'enabled' => env('TEAMS_NESTING', false),
        'max_depth' => env('TEAMS_NESTING_MAX_DEPTH', 4),
        'inherit_roles_down' => env('TEAMS_INHERIT_ROLES_DOWN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | permissions-for-laravel bridge
    |--------------------------------------------------------------------------
    |
    | Off by default. When on, roles and permissions granted to a member with
    | permissions-for-laravel inside a team (Permissions::for($user)->in($team),
    | or Teams::for($team)->members()->grant($user)) count in the team's own
    | checks: memberHasPermission(), memberHasRole(), the teams.<permission>
    | Gate abilities and the Blade directives. They count only while the
    | membership is active. Scope-less Gate checks use the current team, a
    | removed or pruned member loses the team's grants and a force-deleted team
    | forgets every grant made in it.
    |
    | It needs roundly-consulting/permissions-for-laravel ^2.1 with
    | "permissions.assignments.enabled" on (and its migrations published and
    | run). If either is missing the application fails at boot rather than
    | ignoring the switch.
    |
    */

    'permissions' => [
        'bridge' => env('TEAMS_PERMISSIONS_BRIDGE', false),
    ],

];
