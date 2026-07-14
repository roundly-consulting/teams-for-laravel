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
use RoundlyConsulting\Teams\Database\Factories\MemberFactory;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\Roles;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;
use RoundlyConsulting\Teams\Support\InviteModel;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * @property int $id
 * @property int $team_id
 * @property string $member_type
 * @property int $member_id
 * @property string|null $role
 * @property int|null $accepted_invite_id
 * @property Collection<string, mixed> $meta
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * Deliberately not final: `teams.models.member` documents pointing the package at
 * your own subclass, which final would forbid.
 */
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    use Prunable;
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
            'expires_at' => 'datetime',
            'accepted_invite_id' => 'integer',
        ];
    }

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamModel::class());
    }

    /** @return MorphTo<Model, $this> */
    public function member(): MorphTo
    {
        return $this->morphTo('member');
    }

    /** @return BelongsTo<Invite, $this> */
    public function acceptedInvite(): BelongsTo
    {
        return $this->belongsTo(InviteModel::class(), 'accepted_invite_id');
    }

    /**
     * Resolve the member's role, layering per-team overrides over the global
     * provider. Expired memberships resolve to no role.
     */
    public function role(): ?Role
    {
        if ($this->role === null || $this->isExpired()) {
            return null;
        }

        // With per-team overrides off, resolve against the global provider
        // directly so no team relation is loaded (BC: zero extra queries).
        if (! config('teams.roles.per_team', false)) {
            return Roles::find($this->role);
        }

        /** @var Team $team */
        $team = $this->team;

        return app(TeamRoleResolver::class)->resolve($team, $this->role);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * Active memberships lapsing within the next $days — excludes already-expired
     * (the prune path's concern) and never-expiring rows.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    public function removeFromTeam(): void
    {
        if ($this->delete()) {
            TeamMemberDeleted::dispatch($this);
        }
    }

    /** @return Builder<Member> */
    public function prunable(): Builder
    {
        /** @var string $after */
        $after = config('teams.members.prune_after', '30 days');

        return self::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->sub($after));
    }
}
