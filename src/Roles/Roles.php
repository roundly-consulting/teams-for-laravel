<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

final class Roles
{
    /** @var array<string, Role> */
    protected static array $roles = [];

    /** @param list<string> $permissions */
    public static function register(string $key, string $name, array $permissions = []): Role
    {
        return self::$roles[$key] ??= new Role($key, $name, $permissions);
    }

    public static function find(string $key): ?Role
    {
        return self::$roles[$key] ?? null;
    }

    /** @return array<string, Role> */
    public static function all(): array
    {
        return self::$roles;
    }
}
