<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Bridges\Permissions;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Permissions\DataTransferObjects\Assignment;
use RoundlyConsulting\Permissions\Models\Permission;
use RoundlyConsulting\Permissions\Models\Role;
use RoundlyConsulting\Permissions\PermissionsManager;
use RoundlyConsulting\Teams\Bridges\Permissions\Actions\GrantMemberAuthorizationAction;
use RoundlyConsulting\Teams\Bridges\Permissions\Actions\RevokeMemberAuthorizationAction;
use RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects\GrantMemberAuthorizationData;
use RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects\RevokeMemberAuthorizationData;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberGrant;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberRevoke;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Exceptions\MemberNotFoundException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::for($team)->members()->grant($member)` — one member's permissions-for-laravel roles
 * and permissions inside this team (`teams.permissions.bridge`):
 *
 *     Teams::for($team)->members()->grant($user)->until($friday)->assignRole('reviewer');
 *
 * Sugar over `Permissions::for($member)->in($team)`, with two team rules on top: an addition
 * needs a current (or booked) membership — anything else throws MemberNotFoundException — and
 * it ends when the membership expires unless `until()` says otherwise. Every write runs
 * through the teams manager, so `Teams::fake()` records it (and `Permissions::fake()` sees the
 * underlying write too).
 *
 * The withers return a new handle; the handle itself is immutable.
 */
final readonly class MemberGrants
{
    public function __construct(
        private TeamsManager $manager,
        private Team $team,
        private Model $member,
        private ?DateTimeInterface $from = null,
        private ?DateTimeInterface $until = null,
        private bool $untilGiven = false,
    ) {}

    /**
     * Additions start counting at this moment (inclusive).
     */
    public function from(?DateTimeInterface $from): self
    {
        return new self($this->manager, $this->team, $this->member, $from, $this->until, $this->untilGiven);
    }

    /**
     * Additions stop counting at this moment (exclusive). Null: no end — the grant still
     * counts only while the membership is active. Without a call, the membership's expiry.
     */
    public function until(?DateTimeInterface $until): self
    {
        return new self($this->manager, $this->team, $this->member, $this->from, $until, true);
    }

    /**
     * Both ends of the window at once.
     */
    public function between(?DateTimeInterface $from, ?DateTimeInterface $until): self
    {
        return new self($this->manager, $this->team, $this->member, $from, $until, true);
    }

    /**
     * Add roles in the team (additive).
     *
     * @param  string|BackedEnum|Role|iterable<mixed>  ...$roles
     *
     * @throws MemberNotFoundException
     */
    public function assignRole(string|BackedEnum|Role|iterable ...$roles): Model
    {
        return $this->grant(MemberGrant::AssignRole, $roles);
    }

    /**
     * Add direct permissions in the team (additive).
     *
     * @param  string|BackedEnum|Permission|iterable<mixed>  ...$permissions
     *
     * @throws MemberNotFoundException
     */
    public function givePermissionTo(string|BackedEnum|Permission|iterable ...$permissions): Model
    {
        return $this->grant(MemberGrant::GivePermission, $permissions);
    }

    /**
     * Make these the member's complete role set in the team.
     *
     * @param  iterable<mixed>  $roles
     *
     * @throws MemberNotFoundException
     */
    public function syncRoles(iterable $roles): Model
    {
        return $this->grant(MemberGrant::SyncRoles, self::listOf($roles));
    }

    /**
     * Make these the member's complete direct-permission set in the team.
     *
     * @param  iterable<mixed>  $permissions
     *
     * @throws MemberNotFoundException
     */
    public function syncPermissions(iterable $permissions): Model
    {
        return $this->grant(MemberGrant::SyncPermissions, self::listOf($permissions));
    }

    /**
     * Remove roles in the team, every window of them.
     *
     * @param  string|BackedEnum|Role|iterable<mixed>  ...$roles
     */
    public function removeRole(string|BackedEnum|Role|iterable ...$roles): Model
    {
        return $this->revoke(MemberRevoke::RemoveRole, $roles);
    }

    /**
     * Revoke direct permissions in the team, every window of them.
     *
     * @param  string|BackedEnum|Permission|iterable<mixed>  ...$permissions
     */
    public function revokePermissionTo(string|BackedEnum|Permission|iterable ...$permissions): Model
    {
        return $this->revoke(MemberRevoke::RevokePermission, $permissions);
    }

    /**
     * Remove every role and direct permission the member holds in the team.
     */
    public function forgetAllAuthorization(): Model
    {
        return $this->revoke(MemberRevoke::ForgetAll, []);
    }

    /**
     * Whether permissions-for-laravel gives the member this role in the team right now (the
     * membership active). The team's own membership role is not consulted — that is
     * `$team->memberHasRole()`, which counts both.
     */
    public function hasRole(string|BackedEnum $role): bool
    {
        return PermissionsBridge::memberHasRole($this->team, $this->member, self::name($role));
    }

    /**
     * Whether permissions-for-laravel grants the member this permission in the team right
     * now (the membership active). `$team->memberHasPermission()` counts the membership role too.
     */
    public function hasPermissionTo(string|BackedEnum $permission): bool
    {
        return PermissionsBridge::memberHasPermission($this->team, $this->member, self::name($permission));
    }

    /**
     * The member's assignments in this team, one entry per grant and window.
     *
     * @return Collection<int, Assignment>
     */
    public function assignments(): Collection
    {
        return app(PermissionsManager::class)->for($this->member)->in($this->team)->assignments();
    }

    /**
     * @param  array<mixed>  $items
     */
    private function grant(MemberGrant $verb, array $items): Model
    {
        $items = array_values($items);

        return $this->manager->perform(
            TeamOperation::GrantMemberAuthorization,
            GrantMemberAuthorizationAction::class,
            fn (GrantMemberAuthorizationAction $action): Model => $action->execute($this->team, new GrantMemberAuthorizationData(
                member: $this->member,
                verb: $verb,
                items: $items,
                from: $this->from,
                until: $this->until,
                untilGiven: $this->untilGiven,
            )),
            ['team' => $this->team, 'member' => $this->member, 'verb' => $verb->value, 'names' => self::names($items)],
        );
    }

    /**
     * @param  array<mixed>  $items
     */
    private function revoke(MemberRevoke $verb, array $items): Model
    {
        $items = array_values($items);

        return $this->manager->perform(
            TeamOperation::RevokeMemberAuthorization,
            RevokeMemberAuthorizationAction::class,
            fn (RevokeMemberAuthorizationAction $action): Model => $action->execute($this->team, new RevokeMemberAuthorizationData(
                member: $this->member,
                verb: $verb,
                items: $items,
            )),
            ['team' => $this->team, 'member' => $this->member, 'verb' => $verb->value, 'names' => self::names($items)],
        );
    }

    /**
     * @param  iterable<mixed>  $items
     * @return list<mixed>
     */
    private static function listOf(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }

    /**
     * The names in a (nested) list of grants, for the fake's record.
     *
     * @param  iterable<mixed>  $items
     * @return list<string>
     */
    private static function names(iterable $items): array
    {
        $names = [];

        foreach ($items as $item) {
            if (is_iterable($item)) {
                array_push($names, ...self::names($item));
            } elseif ($item instanceof Role || $item instanceof Permission) {
                $names[] = (string) $item->name;
            } elseif (is_string($item) || $item instanceof BackedEnum) {
                $names[] = self::name($item);
            }
        }

        return $names;
    }

    private static function name(string|BackedEnum $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }
}
