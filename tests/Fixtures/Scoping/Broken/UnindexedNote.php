<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Broken;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

final class UnindexedNote extends Model
{
    use ScopedToTeam;

    /** @var string */
    protected $table = 'unindexed_notes';
}
