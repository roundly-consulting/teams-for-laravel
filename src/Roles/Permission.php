<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

final readonly class Permission
{
    public function __construct(
        public string $key,
        public string $name = '',
    ) {}
}
