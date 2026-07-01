<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Approvals\Interfaces\RequiresApprovalInterface;
use RoundlyConsulting\Approvals\Traits\RequiresApproval;
use RoundlyConsulting\Teams\Database\Factories\JoinRequestFactory;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;

/**
 * @property int $id
 * @property int $team_id
 * @property string $requester_type
 * @property int $requester_id
 * @property string|null $requested_role
 * @property JoinRequestStatus $status
 * @property string|null $message
 * @property Collection<string, mixed> $meta
 * @property string|null $responded_by_type
 * @property int|null $responded_by_id
 * @property CarbonInterface|null $responded_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class JoinRequest extends Model implements RequiresApprovalInterface
{
    /** @use HasFactory<JoinRequestFactory> */
    use HasFactory;

    use Prunable;
    use RequiresApproval;
    use SoftDeletes;

    /** @var string */
    protected $table = 'team_join_requests';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => JoinRequestStatus::class,
            'meta' => 'collection',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): JoinRequestFactory
    {
        return JoinRequestFactory::new();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        /** @var class-string<Team> $model */
        $model = config('teams.models.team', Team::class);

        return $this->belongsTo($model);
    }

    /** @return MorphTo<Model, $this> */
    public function requester(): MorphTo
    {
        return $this->morphTo('requester');
    }

    /** @return MorphTo<Model, $this> */
    public function respondedBy(): MorphTo
    {
        return $this->morphTo('responded_by');
    }

    public function isPending(): bool
    {
        return $this->status === JoinRequestStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  Builder<JoinRequest>  $query
     * @return Builder<JoinRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', JoinRequestStatus::Pending->value);
    }

    /**
     * Pending requests whose expiry has passed — the auto-decline candidates.
     *
     * @param  Builder<JoinRequest>  $query
     * @return Builder<JoinRequest>
     */
    public function scopeExpiredPending(Builder $query): Builder
    {
        return $query->pending()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /** @return Builder<JoinRequest> */
    public function prunable(): Builder
    {
        /** @var string $after */
        $after = config('teams.join_requests.prune_after', '30 days');

        return self::query()
            ->where('status', '!=', JoinRequestStatus::Pending->value)
            ->where('updated_at', '<=', now()->sub($after));
    }
}
