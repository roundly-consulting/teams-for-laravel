<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * How outsiders may join a team.
 *
 * - Open: a join request is accepted immediately (auto-approved) unless the
 *   team also requires approval to join.
 * - Request: a join request is created pending a responder's decision.
 * - InviteOnly: outsiders may not request to join; membership is invite-driven.
 */
enum JoinPolicy: string
{
    use Helpers;

    case Open = 'open';
    case Request = 'request';
    case InviteOnly = 'invite_only';
}
