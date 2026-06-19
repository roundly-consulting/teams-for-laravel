<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

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
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Roles can be defined in code (the "array" driver, fast and version
    | controlled) or stored in the database (the "database" driver, manageable
    | at runtime). The "owner" key is granted to a team's creator/owner, and
    | "admin" is the role a previous owner is demoted to on ownership transfer.
    |
    */

    'roles' => [
        'provider' => env('TEAMS_ROLES_PROVIDER', 'array'),
        'owner' => 'owner',
        'admin' => 'admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invites
    |--------------------------------------------------------------------------
    |
    | "expires_after" is a relative interval used as the default invite expiry
    | when none is supplied, and "code_length" is the length of the generated
    | random invite code.
    |
    */

    'invites' => [
        'expires_after' => env('TEAMS_INVITES_EXPIRES_AFTER', '7 days'),
        'code_length' => (int) env('TEAMS_INVITES_CODE_LENGTH', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate & permissions
    |--------------------------------------------------------------------------
    |
    | When "gate.register" is true the package registers Laravel Gate abilities
    | and Blade directives so you can authorize with $user->can('teams.<perm>',
    | $team) and @teamPermission / @teamRole. "prefix" is the ability name
    | prefix used for those gate abilities.
    |
    */

    'gate' => [
        'register' => (bool) env('TEAMS_REGISTER_GATE', true),
        'prefix' => env('TEAMS_GATE_PREFIX', 'teams'),
    ],

];
