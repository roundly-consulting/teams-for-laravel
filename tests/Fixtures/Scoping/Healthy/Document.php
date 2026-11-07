<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

/**
 * Scoped by a custom column.
 */
final class Document extends Model
{
    use ScopedToTeam;

    public const string TEAM_COLUMN = 'company_id';

    /** @var string */
    protected $table = 'documents';

    /** @var array<string> */
    protected $guarded = [];
}
