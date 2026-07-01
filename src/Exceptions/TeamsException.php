<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RuntimeException;

class TeamsException extends RuntimeException
{
    public static function joinPolicyForbidsRequests(): self
    {
        return new self('This team is invite-only and does not accept join requests.');
    }

    public static function maxSeatsReached(int $maxSeats): self
    {
        return new self("This team has reached its seat limit of {$maxSeats}.");
    }
}
