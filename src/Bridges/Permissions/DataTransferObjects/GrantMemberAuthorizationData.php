<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberGrant;

/**
 * One grant to a team member through the permissions bridge.
 *
 * `$items` are what permissions-for-laravel accepts for the verb: role or permission names,
 * backed enums, models, or lists of them. Without `$untilGiven` the grant ends when the
 * membership expires; with it, `$until` is used as given (null = no end).
 */
final readonly class GrantMemberAuthorizationData
{
    /**
     * @param  list<mixed>  $items
     */
    public function __construct(
        public Model $member,
        public MemberGrant $verb,
        public array $items,
        public ?DateTimeInterface $from = null,
        public ?DateTimeInterface $until = null,
        public bool $untilGiven = false,
    ) {}
}
