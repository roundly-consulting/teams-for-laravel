<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

/**
 * A host event a queued platform listener reacts to.
 */
final readonly class TeamLeft
{
    public function __construct(
        public int|string $teamId,
    ) {}
}
