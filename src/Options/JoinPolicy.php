<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Options;

use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Casts\EnumCast;
use RoundlyConsulting\Teams\Enums\JoinPolicy as JoinPolicyEnum;

/**
 * Per-team join policy. Defaults to Request, which preserves the package's
 * original behaviour (a join request is created pending a decision).
 */
final class JoinPolicy extends BaseOption
{
    public function castAs(): string
    {
        return EnumCast::class.':'.JoinPolicyEnum::class;
    }

    public function default(): JoinPolicyEnum
    {
        return JoinPolicyEnum::Request;
    }
}
