<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberRevoke;

/**
 * One removal of a team member's grants through the permissions bridge. `$items` is empty
 * for {@see MemberRevoke::ForgetAll}.
 */
final readonly class RevokeMemberAuthorizationData
{
    /**
     * @param  list<mixed>  $items
     */
    public function __construct(
        public Model $member,
        public MemberRevoke $verb,
        public array $items = [],
    ) {}
}
