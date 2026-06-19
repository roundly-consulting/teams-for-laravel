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

];
