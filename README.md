# Teams for Laravel

Associate users with teams, roles, permissions, and invitations in Laravel.

This package gives any Eloquent model the ability to own and join teams. Members carry a
role, roles carry permissions, and teams can issue expiring invite codes. Teams, members,
and invites are soft-deletable, and expired invites are automatically prunable.

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

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="teams-config"
```

## Configuration

The published `config/teams.php` lets you swap any of the package's Eloquent models for
your own subclass:

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

];
```

| Key             | Type           | Default          | Purpose                                            |
|-----------------|----------------|------------------|----------------------------------------------------|
| `models.team`   | `class-string` | `Team::class`    | Model used for teams and relationship resolution.  |
| `models.member` | `class-string` | `Member::class`  | Model used for team members.                       |
| `models.invite` | `class-string` | `Invite::class`  | Model used for team invites.                       |

The package works with zero configuration; publish the config only to override a model.

## Usage

### Defining team roles

Roles are registered in-memory (not persisted), typically in a service provider's `boot`
method. A role has a key, a display name, an optional set of permissions, and an optional
description:

```php
use RoundlyConsulting\Teams\Roles\Roles;

Roles::register('admin', 'Admin', ['manage-servers', 'manage-users'])
    ->description('Administrator that can manage servers and users.');

Roles::register('user', 'User'); // no permissions, no description

Roles::all();          // array<string, Role>
Roles::find('admin');  // ?Role
```

### Creating teams

Create teams with Eloquent. The `meta` column stores arbitrary structured data and is cast
to a `Collection`:

```php
use RoundlyConsulting\Teams\Models\Team;

$team = Team::create([
    'name' => 'My Business Team',
    'is_public' => false,
    'meta' => [
        'pricing' => 'per_member',
    ],
]);
```

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

### Invitations

A team can issue an expiring invite code. Invites use soft deletes and the `Prunable`
trait, so invites that expired over a month ago are removed by `php artisan model:prune`.

```php
$invite = $team->invite(now()->addWeek(), 'admin', ['source' => 'email']);

$invite->isExpired(): bool;            // always check before accepting
$invite->acceptBy(Model $member): Member; // adds the member and soft-deletes the invite
```

### Roles and permissions

```php
$role = $team->findMember($user)?->role();

$role->hasPermission(string $name): bool;
$role->hasAnyPermission(array $names): bool;
$role->hasAllPermissions(array $names): bool;
```

### Giving a model multiple teams

Add the `HasTeams` trait to any model that can belong to many teams:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Traits\HasTeams;

class User extends Model
{
    use HasTeams;
}

$user->teams(): MorphMany;                                  // membership relation
$user->belongsToTeam(Team $team): bool;
$user->teamRole(Team $team): ?Role;
$user->hasTeamRole(Team $team, string $role): bool;
$user->hasTeamPermission(Team $team, string $permission): bool;
```

### Associating a model with a current team

Add the `BelongsToTeam` trait and a `team_id` column to give a model an active team:

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

### Events

The package dispatches events you can listen to without forking:

| Event                                                  | Dispatched when                       |
|--------------------------------------------------------|---------------------------------------|
| `RoundlyConsulting\Teams\Events\TeamMemberAdded`       | A member is added to a team.          |
| `RoundlyConsulting\Teams\Events\TeamMemberDeleted`     | A member is removed from a team.      |
| `RoundlyConsulting\Teams\Events\InviteCreated`         | An invite is created.                 |
| `RoundlyConsulting\Teams\Events\InviteAccepted`        | An invite is accepted by a member.    |

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
