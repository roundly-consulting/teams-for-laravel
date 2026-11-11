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
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Teams\Database\Factories\MemberFactory;
use RoundlyConsulting\Teams\Events\MembershipExpired;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;
use RoundlyConsulting\Teams\Support\InviteModel;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Support\TeamsConfig;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * @property int $id
 * @property int|string $team_id
 * @property string $member_type
 * @property int $member_id
 * @property string|null $role
 * @property int|null $accepted_invite_id
 * @property Collection<string, mixed> $meta
 * @property CarbonInterface|null $starts_at
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
            'starts_at' => 'datetime',
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
     * provider. Expired memberships, and ones that have not started yet, resolve to
     * no role.
     */
    public function role(): ?Role
    {
        if ($this->role === null || ! $this->isActive()) {
            return null;
        }

        // With per-team overrides off, resolve against the global provider
        // directly so no team relation is loaded (zero extra queries).
        if (! Config::boolean('teams.roles.per_team')) {
            return app(RoleProvider::class)->find($this->role);
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
     * Whether the membership has begun. Always true while `teams.members.start_dates` is
     * off, and for a membership without a start date.
     */
    public function hasStarted(): bool
    {
        if (! TeamsConfig::memberStartDates()) {
            return true;
        }

        return $this->starts_at === null || ! $this->starts_at->isFuture();
    }

    /**
     * Whether the membership counts right now: started and not expired.
     */
    public function isActive(): bool
    {
        return $this->hasStarted() && ! $this->isExpired();
    }

    /**
     * Memberships that count right now: not expired and — with
     * `teams.members.start_dates` on — already started.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->unexpired()
            ->when(TeamsConfig::memberStartDates(), static function (Builder $query): void {
                $query->where(static function (Builder $query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                });
            });
    }

    /**
     * Memberships that have not expired, started or not — every seat that is taken or
     * booked.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeUnexpired(Builder $query): Builder
    {
        return $query->where(static function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Memberships booked to start later (`teams.members.start_dates`); none while the
     * option is off.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        if (! TeamsConfig::memberStartDates()) {
            return $query->whereKey([]);
        }

        return $query->unexpired()->where('starts_at', '>', now());
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

    /**
     * Remove this membership — `Teams::for($team)->members()->remove($member)`.
     */
    public function removeFromTeam(): bool
    {
        /** @var Team $team */
        $team = $this->team;

        /** @var Model $member */
        $member = $this->member;

        return app(TeamsManager::class)->for($team)->members()->remove($member);
    }

    /**
     * Memberships whose expiry passed more than `teams.members.prune_after` ago —
     * collected by `php artisan model:prune` and by `Teams::members()->prune()`.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->sub(TeamsConfig::membersPruneAfter()));
    }

    /**
     * Announce each pruned membership, whichever path prunes it.
     */
    protected function pruning(): void
    {
        MembershipExpired::dispatch($this);
    }
}
