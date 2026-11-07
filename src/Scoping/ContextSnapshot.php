<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

/**
 * The team context at one moment, so `Teams::runAs()` can put it back exactly — no
 * context, one team or a multi-team set — however the callback ended.
 *
 * @internal
 */
final readonly class ContextSnapshot
{
    /**
     * @param  list<int|string>|null  $keys  null when no context was set
     */
    public function __construct(
        public ?array $keys,
        public bool $multiple,
    ) {}
}
