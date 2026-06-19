<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\Invite;

final class InviteEmailMismatchException extends TeamsException
{
    public static function for(Invite $invite): self
    {
        return new self(trans('teams::errors.invite_email_mismatch', ['email' => (string) $invite->email]));
    }
}
