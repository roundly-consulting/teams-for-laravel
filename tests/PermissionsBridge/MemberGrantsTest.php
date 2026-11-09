<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Permissions\DataTransferObjects\Assignment;
use RoundlyConsulting\Permissions\Exceptions\PermissionException;
use RoundlyConsulting\Permissions\Facades\Permissions;
use RoundlyConsulting\Teams\Bridges\Permissions\Actions\GrantMemberAuthorizationAction;
use RoundlyConsulting\Teams\Bridges\Permissions\Actions\RevokeMemberAuthorizationAction;
use RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects\GrantMemberAuthorizationData;
use RoundlyConsulting\Teams\Bridges\Permissions\DataTransferObjects\RevokeMemberAuthorizationData;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberGrant;
use RoundlyConsulting\Teams\Bridges\Permissions\Enums\MemberRevoke;
use RoundlyConsulting\Teams\Bridges\Permissions\MemberGrants;
use RoundlyConsulting\Teams\Exceptions\MemberNotFoundException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Tests\Fixtures\Permissions\RoleUser;
use RoundlyConsulting\Teams\Tests\User;

enum BridgeRole: string
{
    case Reviewer = 'reviewer';
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'UTC'));

    Permissions::permission('posts.edit');
    Permissions::permission('posts.publish');
    Permissions::role('reviewer');
    Permissions::role('editor');

    $this->team = Team::factory()->create();
    $this->user = RoleUser::create();
    Teams::for($this->team)->members()->add($this->user, 'user');
});

/**
 * @return list<array{name: string, until: string|null, from: string|null}>
 */
function teamAssignments(Team $team, RoleUser $user): array
{
    return Teams::for($team)->members()->grant($user)->assignments()
        ->map(static fn (Assignment $assignment): array => [
            'name' => $assignment->name,
            'from' => $assignment->validFrom?->toDateTimeString(),
            'until' => $assignment->validUntil?->toDateTimeString(),
        ])
        ->sortBy('name')
        ->values()
        ->all();
}

it('is the scoped permissions handle for the member', function (): void {
    expect(Teams::for($this->team)->members()->grant($this->user))->toBeInstanceOf(MemberGrants::class);

    $returned = Teams::for($this->team)->members()->grant($this->user)->assignRole('reviewer');

    expect($returned)->toBe($this->user)
        ->and(Permissions::for($this->user)->in($this->team)->hasRole('reviewer'))->toBeTrue()
        ->and($this->team->memberHasRole($this->user, 'reviewer'))->toBeTrue()
        ->and(teamAssignments($this->team, $this->user))->toBe([['name' => 'reviewer', 'from' => null, 'until' => null]]);
});

it('ends the grant with the membership by default', function (): void {
    $expiry = now()->addMonth();
    Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: $expiry);

    Teams::for($this->team)->members()->grant($this->user)->assignRole('reviewer');

    expect(teamAssignments($this->team, $this->user))->toBe([['name' => 'reviewer', 'from' => null, 'until' => $expiry->toDateTimeString()]]);
});

it('takes an explicit end over the membership expiry, and null as no end', function (): void {
    Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->addMonth());

    Teams::for($this->team)->members()->grant($this->user)->until(now()->addWeek())->assignRole('reviewer');
    Teams::for($this->team)->members()->grant($this->user)->until(null)->assignRole('editor');

    expect(teamAssignments($this->team, $this->user))->toBe([
        ['name' => 'editor', 'from' => null, 'until' => null],
        ['name' => 'reviewer', 'from' => null, 'until' => now()->addWeek()->toDateTimeString()],
    ]);
});

it('takes a start, and both ends at once', function (): void {
    Teams::for($this->team)->members()->grant($this->user)->from(now()->addDay())->givePermissionTo('posts.edit');
    Teams::for($this->team)->members()->grant($this->user)->between(now()->addDays(2), now()->addDays(3))->givePermissionTo('posts.publish');

    expect(teamAssignments($this->team, $this->user))->toBe([
        ['name' => 'posts.edit', 'from' => now()->addDay()->toDateTimeString(), 'until' => null],
        ['name' => 'posts.publish', 'from' => now()->addDays(2)->toDateTimeString(), 'until' => now()->addDays(3)->toDateTimeString()],
    ])
        ->and(Teams::for($this->team)->members()->grant($this->user)->hasPermissionTo('posts.edit'))->toBeFalse();

    $this->travel(1)->days();

    expect(Teams::for($this->team)->members()->grant($this->user)->hasPermissionTo('posts.edit'))->toBeTrue()
        ->and($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeTrue();
});

it('refuses a model that is not a member', function (): void {
    $stranger = RoleUser::create();

    expect(fn () => Teams::for($this->team)->members()->grant($stranger)->assignRole('reviewer'))
        ->toThrow(MemberNotFoundException::class, 'is not a member of team #'.$this->team->getKey());

    expect(Permissions::for($stranger)->in($this->team)->assignments())->toBeEmpty();
});

it('refuses a member of another team — the handle is scoped to its team', function (): void {
    $other = Team::factory()->create();
    $outsider = RoleUser::create();
    Teams::for($other)->members()->add($outsider, 'user');

    expect(fn () => Teams::for($this->team)->members()->grant($outsider)->givePermissionTo('posts.edit'))
        ->toThrow(MemberNotFoundException::class);
});

it('refuses an expired member, and lets a booked one be prepared', function (): void {
    Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->addMinute());
    $this->travel(2)->minutes();

    expect(fn () => Teams::for($this->team)->members()->grant($this->user)->assignRole('reviewer'))
        ->toThrow(MemberNotFoundException::class);

    config()->set('teams.members.start_dates', true);
    $booked = RoleUser::create();
    Teams::for($this->team)->members()->add($booked, 'user', startsAt: now()->addWeek());
    Teams::for($this->team)->members()->grant($booked)->assignRole('reviewer');

    expect(Teams::for($this->team)->members()->grant($booked)->hasRole('reviewer'))->toBeFalse();

    $this->travel(8)->days();

    expect(Teams::for($this->team)->members()->grant($booked)->hasRole(BridgeRole::Reviewer))->toBeTrue();
});

it('syncs roles and permissions within the team only', function (): void {
    $other = Team::factory()->create();
    Teams::for($other)->members()->add($this->user, 'user');
    Teams::for($other)->members()->grant($this->user)->assignRole('reviewer');

    Teams::for($this->team)->members()->grant($this->user)->assignRole('reviewer', 'editor');
    Teams::for($this->team)->members()->grant($this->user)->syncRoles(collect(['editor']));
    Teams::for($this->team)->members()->grant($this->user)->givePermissionTo('posts.edit');
    Teams::for($this->team)->members()->grant($this->user)->syncPermissions(['posts.publish']);

    expect(array_column(teamAssignments($this->team, $this->user), 'name'))->toBe(['editor', 'posts.publish'])
        ->and(Teams::for($other)->members()->grant($this->user)->hasRole('reviewer'))->toBeTrue();
});

it('removes, revokes and forgets — also for someone who already left', function (): void {
    $grants = Teams::for($this->team)->members()->grant($this->user);
    $grants->assignRole(BridgeRole::Reviewer, 'editor');
    $grants->givePermissionTo('posts.edit', 'posts.publish');

    $grants->removeRole('editor');
    $grants->revokePermissionTo('posts.publish');

    expect(array_column(teamAssignments($this->team, $this->user), 'name'))->toBe(['posts.edit', 'reviewer']);

    // Left behind the API's back (no cleanup event), so the rows are still there.
    $this->team->findMember($this->user)?->delete();
    $grants->forgetAllAuthorization();

    expect(Permissions::for($this->user)->in($this->team)->assignments())->toBeEmpty();
});

it('reads the member\'s grants in the team', function (): void {
    $grants = Teams::for($this->team)->members()->grant($this->user);
    $grants->assignRole(Permissions::findRole('reviewer'));

    expect($grants->hasRole('reviewer'))->toBeTrue()
        ->and($grants->hasRole('editor'))->toBeFalse()
        ->and($grants->hasPermissionTo('posts.edit'))->toBeFalse()
        ->and($grants->assignments())->toHaveCount(1);
});

it('passes a holder without HasRoles to permissions, which refuses it', function (): void {
    $plain = User::create();
    Teams::for($this->team)->members()->add($plain, 'user');

    expect(fn () => Teams::for($this->team)->members()->grant($plain)->assignRole('reviewer'))
        ->toThrow(PermissionException::class);
});

it('runs the same code through an injected manager and the raw actions', function (): void {
    app(TeamsManager::class)->for($this->team)->members()->grant($this->user)->assignRole('reviewer');

    app(GrantMemberAuthorizationAction::class)->execute($this->team, new GrantMemberAuthorizationData(
        member: $this->user,
        verb: MemberGrant::GivePermission,
        items: ['posts.edit'],
    ));

    expect(array_column(teamAssignments($this->team, $this->user), 'name'))->toBe(['posts.edit', 'reviewer']);

    app(RevokeMemberAuthorizationAction::class)->execute($this->team, new RevokeMemberAuthorizationData(
        member: $this->user,
        verb: MemberRevoke::ForgetAll,
    ));

    expect(teamAssignments($this->team, $this->user))->toBe([]);
});

describe('Teams::fake()', function (): void {
    it('records grants and removals', function (): void {
        $fake = Teams::fake();

        Teams::for($this->team)->members()->grant($this->user)->assignRole(['reviewer', BridgeRole::Reviewer]);
        Teams::for($this->team)->members()->grant($this->user)->givePermissionTo(Permissions::findPermission('posts.edit'));
        Teams::for($this->team)->members()->grant($this->user)->removeRole('reviewer');

        $fake->assertMemberGranted($this->team, $this->user);
        $fake->assertMemberGranted($this->team, $this->user, 'reviewer');
        $fake->assertMemberGranted($this->team, $this->user, 'posts.edit');
        $fake->assertMemberGrantRevoked($this->team, $this->user, 'reviewer');
        $fake->assertMemberGrantRevoked($this->team, $this->user);

        expect($fake->recorded()[0]->string('verb'))->toBe('assign-role')
            ->and($fake->recorded()[0]->strings('names'))->toBe(['reviewer', 'reviewer']);

        expect(fn () => $fake->assertMemberGranted($this->team, $this->user, 'editor'))->toThrow(AssertionFailedError::class, '[editor]')
            ->and(fn () => $fake->assertMemberGrantRevoked($this->team, $this->user, 'posts.edit'))->toThrow(AssertionFailedError::class)
            ->and(fn () => $fake->assertNothingGranted())->toThrow(AssertionFailedError::class, '2 were recorded')
            ->and(fn () => $fake->assertNoGrantRevoked())->toThrow(AssertionFailedError::class, '1 were recorded');
    });

    it('asserts nothing granted or revoked', function (): void {
        $fake = Teams::fake();

        $fake->assertNothingGranted();
        $fake->assertNoGrantRevoked();

        expect(fn () => $fake->assertMemberGranted($this->team, $this->user))->toThrow(AssertionFailedError::class, 'roles or permissions')
            ->and(fn () => $fake->assertMemberGrantRevoked($this->team, $this->user))->toThrow(AssertionFailedError::class);
    });

    it('records nothing for a refused grant', function (): void {
        $fake = Teams::fake();

        expect(fn () => Teams::for($this->team)->members()->grant(RoleUser::create())->assignRole('reviewer'))
            ->toThrow(MemberNotFoundException::class);

        $fake->assertNothingGranted();
    });
});

it('is seen by Permissions::fake() too, with the team and the membership expiry', function (): void {
    $expiry = now()->addMonth();
    Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: $expiry);
    $fake = Permissions::fake();

    Teams::for($this->team)->members()->grant($this->user)->assignRole('reviewer');
    Teams::for($this->team)->members()->grant($this->user)->forgetAllAuthorization();

    $fake->assertRoleAssigned($this->user, 'reviewer', scope: $this->team, until: $expiry);
    $fake->assertAuthorizationForgotten($this->user, scope: $this->team);
});
