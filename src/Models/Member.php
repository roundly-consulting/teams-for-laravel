<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Database\Factories\MemberFactory;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\Roles;

/**
 * @property int $id
 * @property int $team_id
 * @property string $member_type
 * @property int $member_id
 * @property string|null $role
 * @property Collection<string, mixed> $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var string */
    protected $table = 'team_members';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
        ];
    }

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    /** @return MorphTo<Model, $this> */
    public function member(): MorphTo
    {
        return $this->morphTo('member');
    }

    public function role(): ?Role
    {
        if ($this->role === null) {
            return null;
        }

        return Roles::find($this->role);
    }

    public function removeFromTeam(): void
    {
        $this->delete();

        TeamMemberDeleted::dispatch($this);
    }
}
