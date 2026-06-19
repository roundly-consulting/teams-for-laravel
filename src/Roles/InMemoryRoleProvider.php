<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

final class InMemoryRoleProvider implements RoleProvider
{
    /** @var array<string, Role> */
    private array $roles = [];

    /** @param list<string|Permission> $permissions */
    public function register(string $key, string $name, array $permissions = []): Role
    {
        return $this->roles[$key] ??= new Role($key, $name, $permissions);
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
