<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;

final readonly class CreateTeamData
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public string $name,
        public bool $isPublic = false,
        public ?Model $owner = null,
        public array $meta = [],
    ) {}
}
