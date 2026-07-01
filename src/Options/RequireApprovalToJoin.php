<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * When true, an Open team still holds join requests pending a decision instead
 * of auto-approving them. Defaults to false (no change to Open behaviour).
 */
final class RequireApprovalToJoin extends BaseOption
{
    public function castAs(): string
    {
        return 'boolean';
    }

    public function default(): bool
    {
        return false;
    }
}
