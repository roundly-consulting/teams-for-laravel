<?php

declare(strict_types=1);

use RoundlyConsulting\Permissions\Facades\Permissions;
use RoundlyConsulting\Permissions\Models\PermissionAssignment;
use RoundlyConsulting\Permissions\Models\RoleAssignment;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Permissions\RoleUser;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    Permissions::permission('posts.edit');
    Permissions::role('reviewer');

    $this->team = Team::factory()->create();
    $this->other = Team::factory()->create();
    $this->user = RoleUser::create();

    foreach ([$this->team, $this->other] as $team) {
        Teams::for($team)->members()->add($this->user, 'user');
        Permissions::for($this->user)->in($team)->givePermissionTo('posts.edit');
        Permissions::for($this->user)->in($team)->assignRole('reviewer');
    }

    Permissions::for($this->user)->givePermissionTo('posts.edit');
});

/**
 * Assignment rows in the team's scope — counted on the tables, so a soft-deleted team (which
 * an `assignments()` listing shows without its scope) still counts.
 */
function grantsIn(Team $team, RoleUser $user): int
{
    $count = 0;

    foreach ([RoleAssignment::class, PermissionAssignment::class] as $model) {
        $count += $model::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->where('scope_type', $team->getMorphClass())
            ->where('scope_id', $team->getKey())
            ->count();
    }

    return $count;
}

it('forgets the member\'s grants in the team when the member is removed', function (): void {
    Teams::for($this->team)->members()->remove($this->user);

    expect(grantsIn($this->team, $this->user))->toBe(0)
        ->and(grantsIn($this->other, $this->user))->toBe(2)
        ->and($this->user->fresh()?->hasPermissionTo('posts.edit'))->toBeTrue();
});

it('forgets them for a removal through the model method too', function (): void {
    $this->team->removeMember($this->user);

    expect(grantsIn($this->team, $this->user))->toBe(0);
});

it('forgets the grants of a pruned membership', function (): void {
    $this->team->findMember($this->user)?->update(['expires_at' => now()->subYear()]);

    Teams::members()->prune();

    expect($this->team->findMember($this->user))->toBeNull()
        ->and(grantsIn($this->team, $this->user))->toBe(0)
        ->and(grantsIn($this->other, $this->user))->toBe(2);
});

it('forgets the grants of a lapsed membership when it is revived, as teams starts it afresh', function (): void {
    $this->team->findMember($this->user)?->update(['expires_at' => now()->subDay()]);

    Teams::for($this->team)->members()->add($this->user, 'user');

    expect($this->team->hasActiveMember($this->user))->toBeTrue()
        ->and(grantsIn($this->team, $this->user))->toBe(0)
        ->and($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeTrue() // the global grant
        ->and($this->team->memberHasRole($this->user, 'reviewer'))->toBeFalse()
        ->and(grantsIn($this->other, $this->user))->toBe(2);
});

it('keeps what was granted ahead of a brand-new membership, and on an idempotent re-add', function (): void {
    $newcomer = RoleUser::create();
    Permissions::for($newcomer)->in($this->team)->assignRole('reviewer');

    Teams::for($this->team)->members()->add($newcomer, 'user');
    Teams::for($this->team)->members()->add($newcomer, 'admin');

    expect(grantsIn($this->team, $newcomer))->toBe(1)
        ->and($this->team->memberHasRole($newcomer, 'reviewer'))->toBeTrue();
});
it('forgets every grant made in a force-deleted team, and keeps them for a soft-deleted one', function (): void {
    $this->team->delete();

    expect(grantsIn($this->team, $this->user))->toBe(2);

    $this->team->forceDelete();

    expect(RoleAssignment::query()->where('scope_id', $this->team->getKey())->where('scope_type', $this->team->getMorphClass())->count())->toBe(0)
        ->and(PermissionAssignment::query()->where('scope_id', $this->team->getKey())->where('scope_type', $this->team->getMorphClass())->count())->toBe(0)
        ->and(grantsIn($this->other, $this->user))->toBe(2);
});

it('forgets the grants of a member removed from a soft-deleted team', function (): void {
    $membership = $this->team->findMember($this->user);
    $this->team->delete();

    $membership?->delete();
    TeamMemberDeleted::dispatch($membership);

    expect(grantsIn($this->team, $this->user))->toBe(0);
});

it('skips members that cannot hold grants, or whose model is gone', function (): void {
    $plain = User::create();
    Teams::for($this->team)->members()->add($plain, 'user');

    expect(Teams::for($this->team)->members()->remove($plain))->toBeTrue();

    $ghost = Member::factory()->for($this->team)->create(['member_type' => 'no-such-model', 'member_id' => 1]);
    TeamMemberDeleted::dispatch($ghost);

    $gone = RoleUser::create();
    Teams::for($this->team)->members()->add($gone, 'user');
    $membership = $this->team->findMember($gone);
    $gone->delete();
    TeamMemberDeleted::dispatch($membership);

    $orphan = Member::factory()->create([
        'member_type' => $this->user->getMorphClass(),
        'member_id' => $this->user->getKey(),
        'team_id' => Team::factory()->create()->getKey(),
    ]);
    $orphan->team()->getRelated()->newQuery()->withTrashed()->whereKey($orphan->team_id)->forceDelete();
    TeamMemberDeleted::dispatch($orphan);

    expect(grantsIn($this->team, $this->user))->toBe(2);
});
