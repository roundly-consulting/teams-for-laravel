<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Enums;

enum JoinRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
