<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\Invite;

final class InviteExhaustedException extends TeamsException
{
    public static function for(Invite $invite): self
    {
        return new self(trans('teams::errors.invite_exhausted', ['code' => $invite->code]));
    }
}
