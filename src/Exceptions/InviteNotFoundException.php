<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\Invite;

final class InviteNotFoundException extends TeamsException
{
    public static function forCode(string $code): self
    {
        return new self(trans('teams::errors.invite_not_found', ['code' => $code]));
    }

    /**
     * The invite was revoked or deleted after the caller loaded it. Named by id,
     * never by code.
     */
    public static function unavailable(Invite $invite): self
    {
        return new self(trans('teams::errors.invite_unavailable', ['id' => (string) $invite->getKey()]));
    }

    /**
     * A team-scoped handle was handed another team's invite. The message names the
     * invite by id, never by its code — the code is the credential.
     */
    public static function inTeam(Invite $invite): self
    {
        return new self(trans('teams::errors.invite_not_in_team', ['id' => (string) $invite->getKey()]));
    }
}
