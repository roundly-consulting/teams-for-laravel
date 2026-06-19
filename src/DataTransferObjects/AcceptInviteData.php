<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;

final readonly class AcceptInviteData
{
    public function __construct(
        public Model $member,
        public ?string $email = null,
    ) {}
}
