<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Broken;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

final class ColumnlessNote extends Model
{
    use ScopedToTeam;

    /** @var string */
    protected $table = 'columnless_notes';
}
