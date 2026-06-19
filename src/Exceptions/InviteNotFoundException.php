<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

final class InviteNotFoundException extends TeamsException
{
    public static function forCode(string $code): self
    {
        return new self(trans('teams::errors.invite_not_found', ['code' => $code]));
    }
}
