<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Database\Factories\TeamFactory;
use RoundlyConsulting\Teams\Events\InviteCreated;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_public
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
        return $this->findMember($member)
            ?->role()
            ?->hasPermission($permission) ?: false;
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
        /** @var Invite $invite */
        $invite = $this->invites()
            ->create([
                'meta' => new Collection($meta),
                'code' => Str::random(32),
                'role' => $role,
                'expires_at' => $expiresAt,
            ]);

        InviteCreated::dispatch($invite);

        return $invite;
    }

    /** @param array<string, mixed> $meta */
    public function addMember(Model $member, string $role, array $meta = []): Member
    {
        /** @var Member $created */
        $created = $this->members()
            ->create([
                'member_type' => $member->getMorphClass(),
                'member_id' => $member->getKey(),
                'role' => $role,
                'meta' => new Collection($meta),
            ]);

        TeamMemberAdded::dispatch($created);

        return $created;
    }

    public function removeMember(Model $member): bool
    {
        $found = $this->findMember($member);

        if ($found === null) {
            return false;
        }

        $hasBeenDeleted = (bool) $found->delete();

        if ($hasBeenDeleted) {
            TeamMemberDeleted::dispatch($found);
        }

        return $hasBeenDeleted;
    }
}
