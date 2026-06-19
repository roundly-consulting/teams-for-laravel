<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use RoundlyConsulting\Teams\Roles\Permission;

final readonly class DefineTeamRoleData
{
    /** @param list<string|Permission> $permissions */
    public function __construct(
        public int $teamId,
        public string $key,
        public string $name,
        public array $permissions = [],
        public string $description = '',
    ) {}
}
