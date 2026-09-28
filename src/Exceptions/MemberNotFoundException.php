<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\Team;

final class MemberNotFoundException extends TeamsException
{
    public static function inTeam(Team $team): self
    {
        return new self(trans('teams::errors.member_not_found', ['team' => (string) $team->getKey()]));
    }
}
