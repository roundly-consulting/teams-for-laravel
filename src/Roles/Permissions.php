<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

final class Permissions
{
    public static function register(string $key, string $name = '', string $group = ''): Permission
    {
        return self::registry()->register($key, $name, $group);
    }

    /** @param list<string|Permission> $permissions */
    public static function group(string $group, array $permissions): void
    {
        self::registry()->group($group, $permissions);
    }

    public static function find(string $key): ?Permission
    {
        return self::registry()->find($key);
    }

    /** @return array<string, Permission> */
    public static function all(): array
    {
        return self::registry()->all();
    }

    /** @return array<string, Permission> */
    public static function fromRoles(): array
    {
        return self::registry()->fromRoles();
    }

    public static function registry(): PermissionRegistry
    {
        return app(PermissionRegistry::class);
    }
}
