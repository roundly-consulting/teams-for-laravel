<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Teams\Traits\BelongsToTeam;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

/**
 * A host's team-scoped model, with the BelongsToTeam relation alongside.
 */
class Project extends Model
{
    use BelongsToTeam;
    use ScopedToTeam;
    use SoftDeletes;

    /** @var string */
    protected $table = 'projects';

    /** @var array<string> */
    protected $guarded = [];

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
