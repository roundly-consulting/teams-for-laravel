<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Permissions;

use RoundlyConsulting\Permissions\Concerns\HasRoles;
use RoundlyConsulting\Teams\Tests\User;

/**
 * A host user that is both a team member and a permissions-for-laravel role holder.
 */
class RoleUser extends User
{
    use HasRoles;

    /** @var string */
    protected $table = 'users';
}
