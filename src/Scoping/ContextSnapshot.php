<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

/**
 * The team context at one moment, so `Teams::runAs()` can put it back exactly — no
 * context, one team or a multi-team set, with the member it was checked for — however the
 * callback ended.
 *
 * @internal
 */
final readonly class ContextSnapshot
{
    /**
     * @param  list<int|string>|null  $keys  null when no context was set
     * @param  ContextActor|null  $actor  the member the context was checked for, if any
     */
    public function __construct(
        public ?array $keys,
        public bool $multiple,
        public ?ContextActor $actor = null,
    ) {}
}
