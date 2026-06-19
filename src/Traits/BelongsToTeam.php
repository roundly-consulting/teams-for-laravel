<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Teams\Models\Team;

trait BelongsToTeam
{
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        /** @var class-string<Team> $model */
        $model = config('teams.models.team', Team::class);

        return $this->belongsTo($model);
    }

    public function switchTeamTo(Team $team): self
    {
        $this->team()->associate($team);
        $this->save();

        return $this;
    }
}
