<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles\Contracts;

use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;

interface RoleProvider
{
    /**
     * Define a role — an upsert on `$key`: registering a key again replaces its
     * name, permissions and description, whichever provider backs it.
     *
     * @param  list<string|Permission>  $permissions
     */
    public function register(string $key, string $name, array $permissions = [], string $description = ''): Role;

    public function find(string $key): ?Role;

    /** @return array<string, Role> */
    public function all(): array;
}
