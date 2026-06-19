<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\Actions\CreateInviteAction;
use RoundlyConsulting\Teams\Actions\RemoveMemberAction;
use RoundlyConsulting\Teams\Database\Factories\TeamFactory;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_public
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property Collection<string, mixed> $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var string */
    protected $table = 'teams';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
            'is_public' => 'boolean',
        ];
    }

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        /** @var class-string<Member> $model */
        $model = config('teams.models.member', Member::class);

        return $this->hasMany($model);
    }

    /** @return HasMany<Invite, $this> */
    public function invites(): HasMany
    {
        /** @var class-string<Invite> $model */
        $model = config('teams.models.invite', Invite::class);

        return $this->hasMany($model);
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    public function isOwnedBy(Model $owner): bool
    {
        return $this->owner_type === $owner->getMorphClass()
            && (string) $this->owner_id === (string) $owner->getKey();
    }

    /**
     * @param  Builder<Team>  $query
     * @return Builder<Team>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @param  Builder<Team>  $query
     * @return Builder<Team>
     */
    public function scopeWithMember(Builder $query, Model $member): Builder
    {
        return $query->whereHas('members', function (Builder $members) use ($member): void {
            $members->whereMorphedTo('member', $member);
        });
    }

    public function hasMember(Model $member): bool
    {
        return $this->members()
            ->whereMorphedTo('member', $member)
            ->exists();
    }

    public function memberHasRole(Model $member, string $role): bool
    {
        return $this->members()
            ->whereMorphedTo('member', $member)
            ->where('role', $role)
            ->exists();
    }

    public function memberHasPermission(Model $member, string $permission): bool
    {
        $role = $this->findMember($member)?->role();

        if ($role === null) {
            return false;
        }

        return $role->hasPermission($permission);
    }

    public function findMember(Model $member): ?Member
    {
        /** @var ?Member $found */
        $found = $this->members()
            ->whereMorphedTo('member', $member)
            ->first();

        return $found;
    }

    /** @param array<string, mixed> $meta */
    public function invite(Carbon $expiresAt, string $role, array $meta = []): Invite
    {
        return app(CreateInviteAction::class)->execute($this, new CreateInviteData(
            role: $role,
            expiresAt: $expiresAt,
            meta: $meta,
        ));
    }

    /** @param array<string, mixed> $meta */
    public function addMember(Model $member, string $role, array $meta = []): Member
    {
        return app(AddMemberAction::class)->execute($this, new AddMemberData(
            member: $member,
            role: $role,
            meta: $meta,
        ));
    }

    public function removeMember(Model $member): bool
    {
        return app(RemoveMemberAction::class)->execute($this, $member);
    }
}
