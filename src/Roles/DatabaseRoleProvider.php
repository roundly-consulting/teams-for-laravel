<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use RoundlyConsulting\Teams\Models\RoleDefinition;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

final class DatabaseRoleProvider implements RoleProvider
{
    /** @var array<string, Role>|null */
    private ?array $cache = null;

    /** @param list<string|Permission> $permissions */
    public function register(string $key, string $name, array $permissions = []): Role
    {
        $definition = RoleDefinition::query()->updateOrCreate(
            ['key' => $key],
            [
                'name' => $name,
                'permissions' => $this->permissionKeys($permissions),
            ],
        );

        $this->cache = null;

        return $this->toRole($definition);
    }

    public function find(string $key): ?Role
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, Role> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $roles = [];

        foreach (RoleDefinition::query()->get() as $definition) {
            $roles[$definition->key] = $this->toRole($definition);
        }

        return $this->cache = $roles;
    }

    private function toRole(RoleDefinition $definition): Role
    {
        /** @var list<string> $permissions */
        $permissions = $definition->permissions->values()->all();

        return new Role(
            $definition->key,
            $definition->name,
            $permissions,
            $definition->description ?? '',
        );
    }

    /**
     * @param  list<string|Permission>  $permissions
     * @return list<string>
     */
    private function permissionKeys(array $permissions): array
    {
        return array_map(
            static fn (string|Permission $permission): string => $permission instanceof Permission
                ? $permission->key
                : $permission,
            $permissions,
        );
    }
}
