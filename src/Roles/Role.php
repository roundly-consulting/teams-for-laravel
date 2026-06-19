<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

final class Role
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $key,
        public string $name,
        public array $permissions,
        public string $description = '',
    ) {}

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function hasPermission(string $name): bool
    {
        return in_array($name, $this->permissions, true);
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
