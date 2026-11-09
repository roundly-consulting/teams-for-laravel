<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class AddMemberData
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  CarbonInterface|null  $startsAt  when the membership begins; needs `teams.members.start_dates`
     */
    public function __construct(
        public Model $member,
        public string $role,
        public array $meta = [],
        public ?CarbonInterface $expiresAt = null,
        public ?int $acceptedInviteId = null,
        public ?CarbonInterface $startsAt = null,
    ) {}
}
