# Teams for Laravel

Associate users with teams, roles, permissions, and invitations in Laravel.

This package gives any Eloquent model the ability to own and join teams. Members carry a
role, roles carry permissions, and teams can issue expiring, email-targeted invites. A
discoverable `Teams` facade and fluent builder express the common operations in a single
line, business logic lives in testable action classes, and roles can be defined in code or
stored in the database. Teams, members, and invites are soft-deletable, and expired invites
are automatically prunable.

## Requirements

- PHP 8.3+
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

## Configuration

The published `config/teams.php`:

```php
<?php

use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

return [

    'models' => [
        'team' => Team::class,
        'member' => Member::class,
        'invite' => Invite::class,
    ],

    'roles' => [
        'provider' => env('TEAMS_ROLES_PROVIDER', 'array'),
        'owner' => 'owner',
        'admin' => 'admin',
    ],

    'invites' => [
        'expires_after' => env('TEAMS_INVITES_EXPIRES_AFTER', '7 days'),
        'code_length' => (int) env('TEAMS_INVITES_CODE_LENGTH', 32),
    ],

    'gate' => [
        'register' => (bool) env('TEAMS_REGISTER_GATE', true),
        'prefix' => env('TEAMS_GATE_PREFIX', 'teams'),
    ],

];
```

| Key                      | Type           | Default         | Env                            | Purpose                                                                       |
|--------------------------|----------------|-----------------|--------------------------------|-------------------------------------------------------------------------------|
| `models.team`            | `class-string` | `Team::class`   | —                              | Model used for teams and relationship resolution.                             |
| `models.member`          | `class-string` | `Member::class` | —                              | Model used for team members.                                                  |
| `models.invite`          | `class-string` | `Invite::class` | —                              | Model used for team invites.                                                  |
| `roles.provider`         | `string`       | `array`         | `TEAMS_ROLES_PROVIDER`         | Role storage driver: `array` (in code) or `database` (persisted).             |
| `roles.owner`            | `string`       | `owner`         | —                              | Role key granted to a team's creator/owner.                                   |
| `roles.admin`            | `string`       | `admin`         | —                              | Role a previous owner is demoted to when ownership is transferred.            |
| `invites.expires_after`  | `string`       | `7 days`        | `TEAMS_INVITES_EXPIRES_AFTER`  | Relative interval used as the default invite expiry when none is supplied.    |
| `invites.code_length`    | `int`          | `32`            | `TEAMS_INVITES_CODE_LENGTH`    | Length of the generated random invite code.                                   |
| `gate.register`          | `bool`         | `true`          | `TEAMS_REGISTER_GATE`          | Register Laravel Gate abilities and Blade directives for team permissions.    |
| `gate.prefix`            | `string`       | `teams`         | `TEAMS_GATE_PREFIX`            | Ability-name prefix, e.g. `teams.manage-billing`.                             |

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
`ChangeMemberRoleAction`, `CreateInviteAction`, `AcceptInviteAction`, `RevokeInviteAction`,
`TransferOwnershipAction`, `PruneInvitesAction`. Their DTOs live in
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

### Managing members

```php
use Illuminate\Database\Eloquent\Model;

$team->addMember(Model $member, string $role, array $meta = []): Member;
$team->removeMember(Model $member): bool; // soft-deletes the membership

$team->findMember(Model $member): ?Member;
$team->hasMember(Model $member): bool;
$team->memberHasRole(Model $member, string $role): bool;
$team->memberHasPermission(Model $member, string $permission): bool;
```

Adding a member is **idempotent**: re-adding an existing member returns the existing
membership and updates its role if the requested role differs (no duplicate rows).

### Invitations

A team can issue an expiring, optionally email-targeted invite. Invites use soft deletes and
the `Prunable` trait, so invites that expired over a month ago are removed by
`php artisan model:prune` (or `php artisan teams:invites:prune`). Invites resolve by their
`code` for route-model binding.

```php
// Default expiry from config; target a specific email.
$invite = Teams::for($team)->invite(role: 'admin', email: 'jane@acme.test');

$invite->isExpired(): bool;
$invite->acceptBy(Model $member, ?string $email = null): Member;
$invite->revoke(): bool;

Invite::query()->pending();               // not yet expired
Invite::query()->forEmail('jane@acme.test');
```

Accepting an **expired** invite throws `InviteExpiredException`; accepting an
email-targeted invite with a non-matching email throws `InviteEmailMismatchException` (both
extend `RoundlyConsulting\Teams\Exceptions\TeamsException`).

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
php artisan teams:roles          # list registered/persisted roles and permissions
php artisan teams:invites:prune  # delete invites that expired over a month ago
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
| `RoundlyConsulting\Teams\Events\InviteRevoked`               | An invite is revoked.                    |
| `RoundlyConsulting\Teams\Events\TeamOwnershipTransferred`    | A team's ownership is transferred.       |

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
