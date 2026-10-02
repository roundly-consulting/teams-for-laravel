<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Database\Factories\InviteFactory;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * @property int $id
 * @property int $team_id
 * @property string $code
 * @property string $role
 * @property string|null $email
 * @property string|null $invited_by_type
 * @property int|null $invited_by_id
 * @property Collection<string, mixed> $meta
 * @property int $uses
 * @property int|null $max_uses
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * Deliberately not final: `teams.models.invite` documents pointing the package at
 * your own subclass, which final would forbid.
 */
class Invite extends Model
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
            'uses' => 'integer',
            'max_uses' => 'integer',
        ];
    }

    protected static function newFactory(): InviteFactory
    {
        return InviteFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamModel::class());
    }

    /** @return MorphTo<Model, $this> */
    public function invitedBy(): MorphTo
    {
        return $this->morphTo('invited_by');
    }

    /**
     * The members who joined the team by accepting this invite — the
     * authoritative seat-pool roster.
     *
     * @return HasMany<Member, $this>
     */
    public function acceptedMembers(): HasMany
    {
        return $this->hasMany(MemberModel::class(), 'accepted_invite_id');
    }

    /**
     * Readable alias of {@see acceptedMembers()} for seat-pool contexts.
     *
     * @return HasMany<Member, $this>
     */
    public function consumers(): HasMany
    {
        return $this->acceptedMembers();
    }

    public function consumedSeats(): int
    {
        return $this->uses;
    }

    public function remainingSeats(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, $this->max_uses - $this->uses);
    }

    public function hasRemainingSeats(): bool
    {
        $remaining = $this->remainingSeats();

        return $remaining === null || $remaining > 0;
    }

    /**
     * @param  Builder<Invite>  $query
     * @return Builder<Invite>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<Invite>  $query
     * @return Builder<Invite>
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        // Invite emails are stored lower-cased by CreateInviteAction.
        return $query->where('email', Str::lower(trim($email)));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->uses >= $this->max_uses;
    }

    /**
     * Accept this invite as `$member` — `Teams::invites()->accept($invite, …)`.
     */
    public function acceptBy(Model $member, ?string $email = null): Member
    {
        return app(TeamsManager::class)->invites()->accept($this, $member, $email);
    }

    /**
     * Rotate the code and extend the expiry — `Teams::for($team)->invites()->resend($invite)`.
     */
    public function resend(): self
    {
        return app(TeamsManager::class)->for($this->owningTeam())->invites()->resend($this);
    }

    /**
     * `Teams::for($team)->invites()->revoke($invite)`.
     */
    public function revoke(): bool
    {
        return app(TeamsManager::class)->for($this->owningTeam())->invites()->revoke($this);
    }

    /** @return Builder<Invite> */
    public function prunable(): Builder
    {
        return self::query()->where('expires_at', '<=', now()->subMonth());
    }

    private function owningTeam(): Team
    {
        /** @var Team $team */
        $team = $this->team;

        return $team;
    }
}
