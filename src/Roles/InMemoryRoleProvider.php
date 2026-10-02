<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

final class InMemoryRoleProvider implements RoleProvider
{
    /** @var array<string, Role> */
    private array $roles = [];

    /**
     * An upsert: the last registration of a key wins, as with the database provider.
     *
     * @param  list<string|Permission>  $permissions
     */
    public function register(string $key, string $name, array $permissions = [], string $description = ''): Role
    {
        return $this->roles[$key] = new Role($key, $name, $permissions, $description);
    }

    public function find(string $key): ?Role
    {
        return $this->roles[$key] ?? null;
    }

    /** @return array<string, Role> */
    public function all(): array
    {
        return $this->roles;
    }
}
