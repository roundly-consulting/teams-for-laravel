<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamModel;

trait BelongsToTeam
{
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamModel::class());
    }

    public function switchTeamTo(Team $team): self
    {
        $this->team()->associate($team);
        $this->save();

        return $this;
    }
}
