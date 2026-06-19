<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class CreateInviteData
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public string $role,
        public ?CarbonInterface $expiresAt = null,
        public ?string $email = null,
        public ?Model $invitedBy = null,
        public array $meta = [],
    ) {}
}
