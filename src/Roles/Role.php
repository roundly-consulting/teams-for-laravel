<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

final class Role
{
    /** @var list<string> */
    public array $permissions;

    /** @var list<Permission> */
    public array $permissionObjects;

    /** @param list<string|Permission> $permissions */
    public function __construct(
        public string $key,
        public string $name,
        array $permissions,
        public string $description = '',
    ) {
        $this->setPermissions($permissions);
    }

    /** @param list<string|Permission> $permissions */
    public function setPermissions(array $permissions): self
    {
        $objects = [];
        $keys = [];

        foreach ($permissions as $permission) {
            $object = $permission instanceof Permission ? $permission : new Permission($permission);
            $objects[] = $object;
            $keys[] = $object->key;
        }

        $this->permissionObjects = $objects;
        $this->permissions = $keys;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function hasPermission(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        return in_array('*', $this->permissions, true)
            || in_array($name, $this->permissions, true);
    }

    /** @param list<string> $names */
    public function hasAnyPermission(array $names): bool
    {
        foreach ($names as $name) {
            if ($this->hasPermission($name)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $names */
    public function hasAllPermissions(array $names): bool
    {
        foreach ($names as $name) {
            if (! $this->hasPermission($name)) {
                return false;
            }
        }

        return true;
    }
}
