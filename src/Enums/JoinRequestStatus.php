<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Enums;

use RoundlyConsulting\Enums\Helpers;

enum JoinRequestStatus: string
{
    use Helpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
