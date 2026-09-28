<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

/**
 * In-memory registry of developer-defined permissions. Permissions are code
 * constants (not tenant data), so there is no database driver.
 */
final class PermissionRegistry
{
    /** @var array<string, Permission> */
    private array $permissions = [];

    public function register(string $key, string $name = '', string $group = ''): Permission
    {
        return $this->permissions[$key] = new Permission($key, $name, $group);
    }

    /**
     * Tag a set of permissions with a UI group. Existing registrations keep
     * their name; new ones register under the given group.
     *
     * @param  list<string|Permission>  $permissions
     */
    public function group(string $group, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                $this->permissions[$permission->key] = new Permission(
                    $permission->key,
                    $permission->name,
                    $group,
                );

                continue;
            }

            $existing = $this->permissions[$permission] ?? null;

            $this->permissions[$permission] = new Permission(
                $permission,
                $existing->name ?? '',
                $group,
            );
        }
    }

    public function find(string $key): ?Permission
    {
        return $this->permissions[$key] ?? null;
    }

    /** @return array<string, Permission> */
    public function all(): array
    {
        return $this->permissions;
    }

    /**
     * Harvest distinct permission keys from the registered roles, returning a
     * permission map for hosts that declared permissions only on roles.
     *
     * @return array<string, Permission>
     */
    public function fromRoles(): array
    {
        $harvested = $this->permissions;

        foreach (app(RoleProvider::class)->all() as $role) {
            foreach ($role->permissionObjects as $permission) {
                $harvested[$permission->key] ??= $permission;
            }
        }

        return $harvested;
    }
}
