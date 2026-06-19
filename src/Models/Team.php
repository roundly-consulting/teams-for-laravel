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
use RoundlyConsulting\Teams\Actions\DefineTeamRoleAction;
use RoundlyConsulting\Teams\Actions\RemoveMemberAction;
use RoundlyConsulting\Teams\Database\Factories\TeamFactory;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;

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

    /** @return HasMany<TeamRole, $this> */
    public function teamRoles(): HasMany
    {
        /** @var class-string<TeamRole> $model */
        $model = config('teams.models.team_role', TeamRole::class);

        return $this->hasMany($model);
    }

    /** @return HasMany<JoinRequest, $this> */
    public function joinRequests(): HasMany
    {
        /** @var class-string<JoinRequest> $model */
        $model = config('teams.models.join_request', JoinRequest::class);

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
        $found = $this->findMember($member);

        if ($found === null || $found->isExpired()) {
            return false;
        }

        $role = $found->role();

        if ($role === null) {
            return false;
        }

        return $role->hasPermission($permission);
    }

    /**
     * The effective role map for this team: global roles merged with this
     * team's per-team overrides (overrides win on key).
     *
     * @return array<string, Role>
     */
    public function roles(): array
    {
        return app(TeamRoleResolver::class)->all($this);
    }

    /** @param list<string|Permission> $permissions */
    public function defineRole(string $key, string $name, array $permissions = [], string $description = ''): TeamRole
    {
        return app(DefineTeamRoleAction::class)->execute(new DefineTeamRoleData(
            teamId: (int) $this->getKey(),
            key: $key,
            name: $name,
            permissions: $permissions,
            description: $description,
        ));
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
    public function invite(Carbon $expiresAt, string $role, array $meta = [], ?int $maxUses = 1): Invite
    {
        return app(CreateInviteAction::class)->execute($this, new CreateInviteData(
            role: $role,
            expiresAt: $expiresAt,
            meta: $meta,
            maxUses: $maxUses,
        ));
    }

    /** @param array<string, mixed> $meta */
    public function addMember(Model $member, string $role, array $meta = [], ?CarbonInterface $expiresAt = null): Member
    {
        return app(AddMemberAction::class)->execute($this, new AddMemberData(
            member: $member,
            role: $role,
            meta: $meta,
            expiresAt: $expiresAt,
        ));
    }

    public function removeMember(Model $member): bool
    {
        return app(RemoveMemberAction::class)->execute($this, $member);
    }
}
