<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;

final readonly class AddMemberData
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public Model $member,
        public string $role,
        public array $meta = [],
    ) {}
}
