<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Database\Factories\TeamRoleFactory;
use RoundlyConsulting\Teams\Roles\Role;

/**
 * @property int $id
 * @property int $team_id
 * @property string $key
 * @property string $name
 * @property Collection<int, string> $permissions
 * @property string $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class TeamRole extends Model
{
    /** @use HasFactory<TeamRoleFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var string */
    protected $table = 'team_role_overrides';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'collection',
        ];
    }

    protected static function newFactory(): TeamRoleFactory
    {
        return TeamRoleFactory::new();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        /** @var class-string<Team> $model */
        $model = config('teams.models.team', Team::class);

        return $this->belongsTo($model);
    }

    public function toRole(): Role
    {
        /** @var list<string> $permissions */
        $permissions = $this->permissions->values()->all();

        return new Role(
            $this->key,
            $this->name,
            $permissions,
            $this->description,
        );
    }
}
