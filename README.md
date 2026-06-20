# Teams for Laravel

Associate users with teams, roles, permissions, and invitations in Laravel.

This package gives any Eloquent model the ability to own and join teams. Members carry a
role, roles carry permissions, and teams can issue expiring, email-targeted invites. A
discoverable `Teams` facade and fluent builder express the common operations in a single
line, business logic lives in testable action classes, and roles can be defined in code or
stored in the database. Teams, members, and invites are soft-deletable, and expired invites
are automatically prunable.

Beyond the basics it supports **per-team role overrides** (tenant-scoped permission sets), a
**permission registry** for building picker UIs, **resend / multi-use invites**, a
first-class **owner** gate ability, **join requests** (the inverse of invites),
**time-boxed memberships**, a **cache-backed** role provider, publishable
controller/policy/notification **stubs**, and **test helpers** (`Teams::fake()` plus Pest
expectation matchers).

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/teams-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="teams-migrations"
php artisan migrate
```

Optionally publish the config file or translations:

```bash
php artisan vendor:publish --tag="teams-config"
php artisan vendor:publish --tag="teams-translations"
```

Optionally publish the ready-to-wire stubs (accept-invite controller + routes, a queued
event subscriber, and a Pest expectations file):

```bash
php artisan vendor:publish --tag="teams-stubs"
```

## Configuration

The published `config/teams.php`:

```php
<?php

use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;

return [

    'models' => [
        'team' => Team::class,
        'member' => Member::class,
        'invite' => Invite::class,
        'team_role' => TeamRole::class,
        'join_request' => JoinRequest::class,
    ],

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

    'invites' => [
        'expires_after' => env('TEAMS_INVITES_EXPIRES_AFTER', '7 days'),
        'code_length' => (int) env('TEAMS_INVITES_CODE_LENGTH', 32),
    ],

    'members' => [
        'prune_after' => env('TEAMS_MEMBERS_PRUNE_AFTER', '30 days'),
        'expiring_within' => (int) env('TEAMS_MEMBERS_EXPIRING_WITHIN', 7),
    ],

    'join_requests' => [
        'prune_after' => env('TEAMS_JOIN_REQUESTS_PRUNE_AFTER', '30 days'),
    ],

    'gate' => [
        'register' => (bool) env('TEAMS_REGISTER_GATE', true),
        'prefix' => env('TEAMS_GATE_PREFIX', 'teams'),
        'owner_ability' => env('TEAMS_GATE_OWNER_ABILITY', 'owner'),
    ],

    'notifications' => [
        'queue_connection' => env('TEAMS_NOTIFY_CONNECTION'),
    ],

];
```

| Key                              | Type           | Default              | Env                            | Purpose                                                                          |
|----------------------------------|----------------|----------------------|--------------------------------|----------------------------------------------------------------------------------|
| `models.team`                    | `class-string` | `Team::class`        | —                              | Model used for teams and relationship resolution.                                |
| `models.member`                  | `class-string` | `Member::class`      | —                              | Model used for team members.                                                     |
| `models.invite`                  | `class-string` | `Invite::class`      | —                              | Model used for team invites.                                                      |
| `models.team_role`               | `class-string` | `TeamRole::class`    | —                              | Model used for per-team role overrides.                                          |
| `models.join_request`            | `class-string` | `JoinRequest::class` | —                              | Model used for join requests.                                                    |
| `roles.provider`                 | `string`       | `array`              | `TEAMS_ROLES_PROVIDER`         | Role storage driver: `array` (in code) or `database` (persisted).                |
| `roles.owner`                    | `string`       | `owner`              | —                              | Role key granted to a team's creator/owner.                                      |
| `roles.admin`                    | `string`       | `admin`              | —                              | Role a previous owner is demoted to when ownership is transferred.               |
| `roles.default`                  | `string`       | `member`             | `TEAMS_DEFAULT_ROLE`           | Role applied when none is supplied (e.g. approving a join request).              |
| `roles.per_team`                 | `bool`         | `false`              | `TEAMS_PER_TEAM_ROLES`         | Enable per-team role overrides. Off = global roles only, zero extra queries.     |
| `roles.cache.enabled`            | `bool`         | `false`              | `TEAMS_ROLES_CACHE`            | Cache the `database` role map, flushing on every role mutation.                  |
| `roles.cache.store`              | `?string`      | `null`               | `TEAMS_ROLES_CACHE_STORE`      | Cache store to use; `null` uses the default store. Tags used when supported.     |
| `roles.cache.key`                | `string`       | `teams.roles`        | `TEAMS_ROLES_CACHE_KEY`        | Cache key for the role map.                                                      |
| `roles.cache.ttl`                | `int`          | `3600`               | `TEAMS_ROLES_CACHE_TTL`        | Cache lifetime in seconds.                                                       |
| `invites.expires_after`          | `string`       | `7 days`             | `TEAMS_INVITES_EXPIRES_AFTER`  | Relative interval used as the default invite expiry when none is supplied.       |
| `invites.code_length`            | `int`          | `32`                 | `TEAMS_INVITES_CODE_LENGTH`    | Length of the generated random invite code.                                      |
| `members.prune_after`            | `string`       | `30 days`            | `TEAMS_MEMBERS_PRUNE_AFTER`    | Interval after a membership's expiry before `teams:members:prune` deletes it.     |
| `members.expiring_within`        | `int`          | `7`                  | `TEAMS_MEMBERS_EXPIRING_WITHIN` | Default window (days) for `teams:members:expiring` and `MembershipExpiringSoon`. |
| `join_requests.prune_after`      | `string`       | `30 days`            | `TEAMS_JOIN_REQUESTS_PRUNE_AFTER` | Interval after a resolved request's update before `model:prune` deletes it.    |
| `gate.register`                  | `bool`         | `true`               | `TEAMS_REGISTER_GATE`          | Register Laravel Gate abilities and Blade directives for team permissions.       |
| `gate.prefix`                    | `string`       | `teams`              | `TEAMS_GATE_PREFIX`            | Ability-name prefix, e.g. `teams.manage-billing`.                                |
| `gate.owner_ability`             | `string`       | `owner`              | `TEAMS_GATE_OWNER_ABILITY`     | Short ability that resolves to team ownership: `teams.owner`.                     |
| `notifications.queue_connection` | `?string`      | `null`               | `TEAMS_NOTIFY_CONNECTION`      | Queue connection consumed by the publishable event-subscriber stub.              |

The package works with zero configuration.

## Usage

### The `Teams` facade and fluent builder

The `Teams` facade is the discoverable entry point. It delegates to the package's action
classes, which you can also resolve and call directly.

```php
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;

// Create a team, optionally wiring in its owner as a member with the owner role.
$team = Teams::createTeam(new CreateTeamData(name: 'Acme', owner: $owner));

// Chain member and invite operations.
Teams::for($team)
    ->addMember($alice, 'admin')
    ->addMember($bob, 'user')
    ->changeRole($bob, 'admin')
    ->transferOwnershipTo($alice);

$invite = Teams::for($team)->invite(role: 'admin', email: 'jane@acme.test');

Teams::role('admin');  // ?Role
Teams::roles();        // array<string, Role>
```

The builder methods are chainable (`addMember`, `removeMember`, `changeRole`,
`transferOwnershipTo`) except `invite()` which returns the created `Invite`, and `team()`
which returns the underlying `Team`.

### Action classes & DTOs

Every operation is also a single-`execute` action that accepts a DTO. Resolve them from the
container:

```php
use RoundlyConsulting\Teams\Actions\CreateInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;

$invite = app(CreateInviteAction::class)->execute($team, new CreateInviteData(
    role: 'admin',
    email: 'jane@acme.test',
    invitedBy: $currentUser,
));
```

Available actions: `CreateTeamAction`, `AddMemberAction`, `RemoveMemberAction`,
`ChangeMemberRoleAction`, `CreateInviteAction`, `AcceptInviteAction`, `ResendInviteAction`,
`RevokeInviteAction`, `TransferOwnershipAction`, `PruneInvitesAction`,
`PruneExpiredMembersAction`, `DispatchExpiringMembershipsAction`, `DefineTeamRoleAction`,
`RequestToJoinAction`, `ApproveJoinRequestAction`, `DenyJoinRequestAction`,
`ExpireJoinRequestsAction`. Their DTOs live in
`RoundlyConsulting\Teams\DataTransferObjects`.

### Defining team roles

Register roles in a service provider's `boot` method. A role has a key, a display name,
optional permissions (strings or `Permission` value objects), and an optional description.
The `*` permission grants everything.

```php
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Roles;

Roles::register('admin', 'Admin', ['*']);
Roles::register('editor', 'Editor', ['edit', new Permission('publish', 'Publish posts')])
    ->description('Can edit and publish content.');
Roles::register('user', 'User');

Roles::all();          // array<string, Role>
Roles::find('admin');  // ?Role
```

#### Storing roles in the database

Set `roles.provider` to `database` and the same `Roles::*` API persists roles to the
`team_roles` table, so admins can manage them at runtime. Permission checks are unchanged.

```php
// config/teams.php → 'roles' => ['provider' => 'database', ...]
Roles::register('editor', 'Editor', ['edit', 'publish']); // upserts a RoleDefinition row
```

#### Per-team role overrides

Enable `roles.per_team` to let each team diverge from the global roles. An override for a
`(team, key)` pair wins over the global definition; everything else falls back to the global
role. With the flag off, resolution is identical to before and issues no extra queries.

```php
// config/teams.php → 'roles' => ['per_team' => true, ...]
Roles::register('editor', 'Editor', ['posts.edit']);          // global baseline

Teams::for($team)->defineRole('editor', 'Editor', ['posts.edit', 'posts.publish']);

$user->can('teams.posts.publish', $team); // true for THIS team only
$team->roles();                           // effective role map (global merged with overrides)
```

#### Caching the database provider

When using the `database` provider, set `roles.cache.enabled` to cache the role map and flush
it automatically on every `Roles::register(...)`. Tag-aware stores are flushed by tag; other
stores fall back to a single key forget. Off by default.

### Enumerating permissions

A permission registry lets you list every known permission — for a role-editor or
permission-picker UI — independently of which roles use them. Permissions are developer-defined
code constants (no database driver).

```php
use RoundlyConsulting\Teams\Roles\Permissions;

Permissions::register('posts.publish', 'Publish posts', group: 'Content');
Permissions::group('Content', ['posts.edit', 'posts.delete']);

Permissions::all();        // array<string, Permission>
Permissions::find('posts.publish');
Permissions::fromRoles();  // harvest distinct permissions declared on registered roles
Teams::permissions();      // same map, via the facade
```

### Creating teams

```php
use RoundlyConsulting\Teams\Models\Team;

$team = Team::create([
    'name' => 'My Business Team',
    'is_public' => false,
    'meta' => ['pricing' => 'per_member'],
]);

// Scopes
Team::query()->public()->get();        // only public teams
Team::query()->withMember($user)->get(); // teams the model belongs to
```

### Ownership

```php
$team->owner();                 // MorphTo relation to the owning model
$team->isOwnedBy($user);        // bool

Teams::for($team)->transferOwnershipTo($newOwner);
```

When ownership is transferred, the new owner is ensured to be a member with the owner role
and the previous owner is demoted to the configured admin role (kept on the team).

A first-class **owner ability** removes hand-rolled "owner-only" permissions:

```php
$user->can('teams.owner', $team);   // true only for the owner
$user->ownsTeam($team);             // via the HasTeams trait
```

```blade
@teamOwner($team)
    <button>Delete team</button>
@endteamOwner
```

### Managing members

```php
use Illuminate\Database\Eloquent\Model;

$team->addMember(Model $member, string $role, array $meta = [], ?CarbonInterface $expiresAt = null): Member;
$team->removeMember(Model $member): bool; // soft-deletes the membership

$team->findMember(Model $member): ?Member;
$team->hasMember(Model $member): bool;
$team->memberHasRole(Model $member, string $role): bool;
$team->memberHasPermission(Model $member, string $permission): bool;
```

Adding a member is **idempotent**: re-adding an existing member returns the existing
membership and updates its role if the requested role differs (no duplicate rows). Re-adding
without an `expiresAt` keeps the existing expiry.

#### Time-boxed memberships

Pass `expiresAt` to grant a temporary membership (contractors, trials). Once expired, the
member resolves **no role and no permissions** — but `hasMember` / `memberHasRole` still match
the row so it remains for audit until pruned.

```php
$team->addMember($contractor, 'editor', expiresAt: now()->addDays(30));

$member->isExpired(): bool;
Member::query()->active();   // expires_at null or in the future
Member::query()->expired();

// Force-delete members expired beyond config('teams.members.prune_after'), firing
// MembershipExpired per row (also collected by `php artisan model:prune`).
php artisan teams:members:prune
```

### Invitations

A team can issue an expiring, optionally email-targeted invite. Invites use soft deletes and
the `Prunable` trait, so invites that expired over a month ago are removed by
`php artisan model:prune` (or `php artisan teams:invites:prune`). Invites resolve by their
`code` for route-model binding.

```php
// Default expiry from config; target a specific email.
$invite = Teams::for($team)->invite(role: 'admin', email: 'jane@acme.test');

// Multi-seat link: usable up to N times (null = unlimited). Defaults to 1 (single use).
$invite = Teams::for($team)->invite(role: 'member', maxUses: 5);

$invite->isExpired(): bool;
$invite->isExhausted(): bool;             // reached its usage limit
$invite->acceptBy(Model $member, ?string $email = null): Member;
$invite->resend(): Invite;                // rotate the code and extend expiry
$invite->revoke(): bool;

// The same resend/revoke operations are also callable from the team builder.
Teams::for($team)->resendInvite($invite); // returns the refreshed Invite
Teams::for($team)->revokeInvite($invite); // returns bool

// Accept in one call by code (resolves the invite for you).
Teams::acceptInviteByCode($code, $user);

Invite::query()->pending();               // not yet expired
Invite::query()->forEmail('jane@acme.test');
```

Multi-use ("seat pool") invites record which members joined through them, so you can render a
seat ledger and audit who consumed a link:

```php
$invite = Teams::for($team)->invite(role: 'member', maxUses: 5);
// ... three people accept ...

$invite->consumedSeats();      // 3
$invite->remainingSeats();     // 2  (null when max_uses is null / unlimited)
$invite->hasRemainingSeats();  // true
$invite->acceptedMembers();    // HasMany<Member> — the roster, eager-loadable / paginatable
$invite->consumers();          // readable alias of acceptedMembers()

// From the member side:
$member->acceptedInvite;       // the Invite this member joined through (or null)
```

Force-deleting (or hard-pruning) an invite nulls `accepted_invite_id` on its members rather
than deleting them, so the membership roster is never lost to invite cleanup.

Each accept increments the invite's `uses`; a single-use invite is deleted on its first
accept (the original behaviour), while a multi-use invite survives until exhausted. Accepting
an **expired** invite throws `InviteExpiredException`; an **exhausted** invite throws
`InviteExhaustedException`; an email-targeted invite with a non-matching email throws
`InviteEmailMismatchException`; and `Teams::acceptInviteByCode` on an unknown code throws
`InviteNotFoundException` (all extend `RoundlyConsulting\Teams\Exceptions\TeamsException`).

Resending an invite fires `InviteResent`. The package ships a publishable accept-invite
controller and route stub (`teams-stubs` tag) — it does not register routes itself, so you
control the URLs.

### Join requests

The inverse of invites: a user asks to join a (public) team and an admin approves or denies.

```php
$request = Teams::requestToJoin($team, $user, requestedRole: 'member', message: 'Please add me');

// Idempotent: a second call for the same (team, user) returns the existing pending request.
Teams::for($team)->approveJoinRequest($request, $admin);   // adds the member, fires JoinRequestApproved
Teams::for($team)->denyJoinRequest($request, $admin);      // fires JoinRequestDenied, no membership

$team->joinRequests()->pending()->get();
```

Approving resolves the role from the responder override, then the requested role, then
`roles.default`. Approving or denying a non-pending request is a guarded no-op.

Join requests can optionally expire. Pass `expiresAt` to set a deadline; a `null` expiry
(the default) never lapses, preserving the original behaviour. `teams:join-requests:prune`
auto-declines any pending request whose expiry has passed — reusing the `Denied` status with a
**null responder** (system-decided) and firing `JoinRequestExpired`:

```php
$request = Teams::requestToJoin($team, $user, requestedRole: 'member', expiresAt: now()->addDays(14));

$request->isExpired();                      // true once the deadline passes
JoinRequest::query()->expiredPending();     // pending requests past their expiry

// Schedule the lifecycle (in routes/console.php):
// Schedule::command('teams:join-requests:prune')->daily();  // auto-decline + JoinRequestExpired
// Schedule::command('model:prune')->daily();                // hard-delete long-resolved rows
```

Resolved requests are hard-deleted by `php artisan model:prune` once older than
`config('teams.join_requests.prune_after')`, mirroring invites and members.

### Expiring-membership reports and renewals

`teams:members:expiring` lists memberships lapsing within a window so you can send renewal
reminders before access is lost. It is **read-only by default** — events fire only with
`--notify`. Already-expired and never-expiring memberships are excluded.

```bash
php artisan teams:members:expiring                 # read-only table, window from config
php artisan teams:members:expiring --days=3        # narrow the window
php artisan teams:members:expiring --days=3 --notify  # fire MembershipExpiringSoon per member
```

Reuse the action from your own scheduled job — no Artisan needed:

```php
use RoundlyConsulting\Teams\Actions\DispatchExpiringMembershipsAction;

app(DispatchExpiringMembershipsAction::class)->execute(withinDays: 7, notify: true);

// or query directly:
Member::query()->expiringWithin(7)->get();
```

Listen for `MembershipExpiringSoon` to turn it into a notification (e.g. in the publishable
`TeamEventSubscriber`).

### Roles and permissions

```php
$role = $team->findMember($user)?->role();

$role->hasPermission(string $name): bool;       // '*' grants everything
$role->hasAnyPermission(array $names): bool;
$role->hasAllPermissions(array $names): bool;
```

### Laravel Gate & Blade integration

With `gate.register` enabled (the default), authorize the idiomatic way. The ability name is
`<prefix>.<permission>` and the team is passed as the gate argument:

```php
if ($user->can('teams.manage-billing', $team)) {
    // ...
}
```

```blade
@teamPermission($team, 'manage-billing')
    <a href="/billing">Billing</a>
@endteamPermission

@teamRole($team, 'admin')
    <button>Manage team</button>
@endteamRole
```

Both directives default to the authenticated user; pass a third argument to check a
specific member. Set `TEAMS_REGISTER_GATE=false` to opt out entirely.

### Giving a model multiple teams

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Traits\HasTeams;

class User extends Model
{
    use HasTeams;
}

$user->teams(): MorphMany;                 // membership relation
$user->ownedTeams(): MorphMany;            // teams this model owns
$user->hasTeam(): bool;
$user->belongsToTeam(Team $team): bool;
$user->isMemberOf(Team $team): bool;       // alias of belongsToTeam
$user->ownsTeam(Team $team): bool;
$user->teamsWithRole(string $role): Collection;
$user->teamRole(Team $team): ?Role;
$user->hasTeamRole(Team $team, string $role): bool;
$user->hasTeamPermission(Team $team, string $permission): bool;
```

### Associating a model with a current team

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Traits\BelongsToTeam;

class User extends Model
{
    use BelongsToTeam;
}

$user->team(): BelongsTo;
$user->switchTeamTo(Team $team): static;
```

### Artisan commands

```bash
php artisan teams:roles                 # list registered/persisted roles and permissions
php artisan teams:permissions           # list registered permissions and their groups
php artisan teams:invites:prune         # delete invites that expired over a month ago
php artisan teams:invites:resend {code} # rotate an invite's code and extend its expiry
php artisan teams:members:prune         # delete members expired beyond the retention window
php artisan teams:members:expiring      # report memberships expiring soon (--days, --notify)
php artisan teams:join-requests:prune   # auto-decline pending requests past their expiry
php artisan teams:policy {name}         # scaffold a team-scoped policy (--force to overwrite)
```

### Policies

`teams:policy` writes a policy extending `RoundlyConsulting\Teams\Policies\AbstractTeamPolicy`,
whose `before()` grants every ability to the team owner. The `HasTeamPolicies` concern keeps
hand-written policies terse:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Concerns\HasTeamPolicies;
use RoundlyConsulting\Teams\Models\Team;

class PostPolicy
{
    use HasTeamPolicies;

    public function update(Model $user, Team $team): bool
    {
        return $this->owns($user, $team) || $this->allows($user, $team, 'posts.update');
    }
}
```

### Notification bridge

Turning team events into notifications is boilerplate every app repeats. Publish the
`teams-stubs` and register `App\Listeners\TeamEventSubscriber` (a queued event subscriber) in
your `EventServiceProvider`, then drop your own `Notification` classes into its handlers —
the package ships the wiring, your app owns the content. It reads
`notifications.queue_connection` for its queue.

### Test helpers

In host-application tests, fake the facade and use the expectation matchers:

```php
use RoundlyConsulting\Teams\Facades\Teams;

Teams::fake();
Teams::for($team)->addMember($user, 'admin');
Teams::assertMemberAdded($team, $user);     // also assertTeamCreated / assertInviteCreated
```

Register the Pest matchers (publish `teams-stubs`, then `require` the published file from
`tests/Pest.php`, or call `TeamExpectations::register()`):

```php
expect($user)
    ->toBeMemberOf($team)
    ->withRole($team, 'admin')
    ->toHaveTeamPermission($team, 'posts.publish');
```

### Events

The package dispatches events you can listen to without forking:

| Event                                                        | Dispatched when                          |
|--------------------------------------------------------------|------------------------------------------|
| `RoundlyConsulting\Teams\Events\TeamMemberAdded`             | A member is added to a team.             |
| `RoundlyConsulting\Teams\Events\TeamMemberDeleted`           | A member is removed from a team.         |
| `RoundlyConsulting\Teams\Events\TeamMemberRoleChanged`       | A member's role changes.                 |
| `RoundlyConsulting\Teams\Events\InviteCreated`               | An invite is created.                    |
| `RoundlyConsulting\Teams\Events\InviteAccepted`              | An invite is accepted by a member.       |
| `RoundlyConsulting\Teams\Events\InviteResent`               | An invite is resent (code rotated).      |
| `RoundlyConsulting\Teams\Events\InviteRevoked`               | An invite is revoked.                    |
| `RoundlyConsulting\Teams\Events\TeamOwnershipTransferred`    | A team's ownership is transferred.       |
| `RoundlyConsulting\Teams\Events\JoinRequestCreated`          | A user requests to join a team.          |
| `RoundlyConsulting\Teams\Events\JoinRequestApproved`         | A join request is approved.              |
| `RoundlyConsulting\Teams\Events\JoinRequestDenied`           | A join request is denied.                |
| `RoundlyConsulting\Teams\Events\JoinRequestExpired`          | A pending join request auto-declines on expiry. |
| `RoundlyConsulting\Teams\Events\MembershipExpired`           | An expired membership is pruned.         |
| `RoundlyConsulting\Teams\Events\MembershipExpiringSoon`      | A membership lapses within the report window (`--notify`). |

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\InviteAccepted;

Event::listen(function (InviteAccepted $event): void {
    // $event->invite, $event->member
});
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security
vulnerabilities.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
