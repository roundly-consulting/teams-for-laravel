<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * Optional cap on a team's active membership count. Null (the default) means
 * unlimited seats, preserving the package's original behaviour.
 */
final class MaxSeats extends BaseOption
{
    public function castAs(): string
    {
        return 'integer';
    }

    public function default(): ?int
    {
        return null;
    }
}
