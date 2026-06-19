<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Database\Factories\InviteFactory;
use RoundlyConsulting\Teams\Events\InviteAccepted;

/**
 * @property int $id
 * @property int $team_id
 * @property string $code
 * @property string $role
 * @property Collection<string, mixed> $meta
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class Invite extends Model
{
    /** @use HasFactory<InviteFactory> */
    use HasFactory;

    use Prunable;
    use SoftDeletes;

    /** @var string */
    protected $table = 'team_invites';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'meta' => 'collection',
        ];
    }

    protected static function newFactory(): InviteFactory
    {
        return InviteFactory::new();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        /** @var class-string<Team> $model */
        $model = config('teams.models.team', Team::class);

        return $this->belongsTo($model);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function acceptBy(Model $member): Member
    {
        /** @var Team $team */
        $team = $this->team;

        $added = $team->addMember($member, $this->role);

        $this->delete();

        InviteAccepted::dispatch($this, $added);

        return $added;
    }

    /** @return Builder<Invite> */
    public function prunable(): Builder
    {
        return self::query()->where('expires_at', '<=', now()->subMonth());
    }
}
