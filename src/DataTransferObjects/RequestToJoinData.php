<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Models\Team;

final readonly class RequestToJoinData
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public Team $team,
        public Model $requester,
        public ?string $requestedRole = null,
        public ?string $message = null,
        public array $meta = [],
        public ?CarbonInterface $expiresAt = null,
    ) {}
}
