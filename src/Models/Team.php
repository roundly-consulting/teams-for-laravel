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
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Traits\HasAddresses;
use RoundlyConsulting\Connections\Concerns\HasConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Contacts\Concerns\HasContacts;
use RoundlyConsulting\Options\Traits\HasOptions;
use RoundlyConsulting\Teams\Database\Factories\TeamFactory;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Support\InviteModel;
use RoundlyConsulting\Teams\Support\JoinRequestModel;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamRoleModel;
use RoundlyConsulting\Teams\TeamsManager;

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
 *
 * The convenience methods below (`addMember`, `removeMember`, `invite`,
 * `defineRole`, `roles`) delegate to {@see TeamsManager} — the same code path as
 * `Teams::for($team)->…`, so `Teams::fake()` records them too.
 *
 * Deliberately not final: `teams.models.team` documents pointing the package at
 * your own subclass, which final would forbid.
 */
class Team extends Model implements Connectable
{
    // Both concerns ship an addAddress(): the structured addresses-package one
    // wins; the contacts free-text variant is kept under a distinct name.
    use HasAddresses, HasContacts {
        HasAddresses::addAddress insteadof HasContacts;
        HasContacts::addAddress as addContactAddress;
    }
    use HasConnections;

    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use HasOptions;
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

    /**
     * Every relation below names `team_id` explicitly. Eloquent derives an unnamed
     * foreign key from the PARENT'S CLASS NAME, so a host that swaps
     * `teams.models.team` for its own subclass — which the config invites — would
     * otherwise get `custom_team_id`, a column no table has.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(MemberModel::class(), 'team_id');
    }

    /** @return HasMany<Invite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(InviteModel::class(), 'team_id');
    }

    /** @return HasMany<TeamRole, $this> */
    public function teamRoles(): HasMany
    {
        return $this->hasMany(TeamRoleModel::class(), 'team_id');
    }

    /** @return HasMany<JoinRequest, $this> */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequestModel::class(), 'team_id');
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

    /**
     * Whether the model holds `$role` on this team right now. An expired membership
     * holds no role (its row stays for audit until pruned — see hasMember()).
     */
    public function memberHasRole(Model $member, string $role): bool
    {
        return $this->members()
            ->active()
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
        return app(TeamsManager::class)->for($this)->roles()->all();
    }

    /** @param list<string|Permission> $permissions */
    public function defineRole(string $key, string $name, array $permissions = [], string $description = ''): TeamRole
    {
        return app(TeamsManager::class)->for($this)->roles()->define($key, $name, $permissions, $description);
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
    public function invite(
        string $role,
        ?CarbonInterface $expiresAt = null,
        ?string $email = null,
        ?Model $invitedBy = null,
        array $meta = [],
        ?int $maxUses = 1,
    ): Invite {
        return app(TeamsManager::class)->for($this)->invites()->create($role, $expiresAt, $email, $invitedBy, $meta, $maxUses);
    }

    /** @param array<string, mixed> $meta */
    public function addMember(Model $member, string $role, array $meta = [], ?CarbonInterface $expiresAt = null): Member
    {
        return app(TeamsManager::class)->for($this)->members()->add($member, $role, $meta, $expiresAt);
    }

    public function removeMember(Model $member): bool
    {
        return app(TeamsManager::class)->for($this)->members()->remove($member);
    }
}
