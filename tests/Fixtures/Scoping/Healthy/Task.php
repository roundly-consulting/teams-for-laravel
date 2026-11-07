<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

final class Task extends Model
{
    use ScopedToTeam;

    /** @var string */
    protected $table = 'tasks';

    /** @var array<string> */
    protected $guarded = [];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
