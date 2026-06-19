<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;

final readonly class RespondToJoinRequestData
{
    public function __construct(
        public Model $responder,
        public ?string $role = null,
    ) {}
}
