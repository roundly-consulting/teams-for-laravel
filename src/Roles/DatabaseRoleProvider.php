<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use RoundlyConsulting\Teams\Models\RoleDefinition;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

/**
 * Roles persisted in `team_roles`. The role map is memoised for the lifetime of the
 * instance — one request or job, since the container binds the provider scoped —
 * and forgotten whenever a definition changes in this process.
 */
final class DatabaseRoleProvider implements RoleProvider
{
    /** @var array<string, Role>|null */
    private ?array $cache = null;

    /**
     * @param  bool  $memoize  false when wrapped by {@see CachedRoleProvider}, whose
     *                         refills must always read the table
     */
    public function __construct(
        private readonly bool $memoize = true,
    ) {}

    /** @param list<string|Permission> $permissions */
    public function register(string $key, string $name, array $permissions = [], string $description = ''): Role
    {
        // Trashed rows included: the unique key index covers them, so a deleted
        // definition is restored and redefined rather than re-inserted.
        $definition = RoleDefinition::query()->withTrashed()->firstOrNew(['key' => $key]);

        $definition->fill([
            'name' => $name,
            'permissions' => $this->permissionKeys($permissions),
            'description' => $description,
        ]);

        if ($definition->trashed()) {
            $definition->restore();
        } else {
            $definition->save();
        }

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

        if ($this->memoize) {
            $this->cache = $roles;
        }

        return $roles;
    }

    /**
     * Forget the memoised role map.
     *
     * @internal called when a role definition changes
     */
    public function flush(): void
    {
        $this->cache = null;
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
