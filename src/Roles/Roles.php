<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

final class Roles
{
    /** @param list<string|Permission> $permissions */
    public static function register(string $key, string $name, array $permissions = []): Role
    {
        return self::provider()->register($key, $name, $permissions);
    }

    public static function find(string $key): ?Role
    {
        return self::provider()->find($key);
    }

    /** @return array<string, Role> */
    public static function all(): array
    {
        return self::provider()->all();
    }

    public static function provider(): RoleProvider
    {
        return app(RoleProvider::class);
    }
}
