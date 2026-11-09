<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\Permissions\Facades\Permissions;
use RoundlyConsulting\Teams\Bridges\Permissions\PermissionsBridge;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Permissions\RoleUser;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    Permissions::permission('posts.edit');
    Permissions::permission('posts.review');
    Permissions::role('reviewer')->givePermissionTo('posts.review');

    $this->team = Team::factory()->create();
    $this->other = Team::factory()->create();
    $this->user = RoleUser::create();

    // The `user` team role carries no permission: whatever the checks below grant comes
    // from permissions-for-laravel.
    Teams::for($this->team)->members()->add($this->user, 'user');
    Teams::for($this->other)->members()->add($this->user, 'user');
});

it('is on', function (): void {
    expect(PermissionsBridge::enabled())->toBeTrue()
        ->and(PermissionsBridge::installed())->toBeTrue();
});

it('counts a permission granted in the team, beside the membership role', function (): void {
    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeFalse();

    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');

    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeTrue()
        ->and($this->user->hasTeamPermission($this->team, 'posts.edit'))->toBeTrue()
        ->and($this->user->can('teams.posts.edit', $this->team))->toBeTrue()
        ->and(Blade::render("@teamPermission(\$team, 'posts.edit', \$user) yes @else no @endteamPermission", ['team' => $this->team, 'user' => $this->user]))->toContain('yes');
});

it('counts a role given in the team and the permissions that role carries', function (): void {
    Permissions::for($this->user)->in($this->team)->assignRole('reviewer');

    expect($this->team->memberHasRole($this->user, 'reviewer'))->toBeTrue()
        ->and($this->user->hasTeamRole($this->team, 'reviewer'))->toBeTrue()
        ->and($this->team->memberHasPermission($this->user, 'posts.review'))->toBeTrue()
        ->and(Blade::render("@teamRole(\$team, 'reviewer', \$user) yes @else no @endteamRole", ['team' => $this->team, 'user' => $this->user]))->toContain('yes');
});

it('keeps the membership role answer', function (): void {
    Teams::for($this->team)->members()->changeRole($this->user, 'admin');

    expect($this->team->memberHasRole($this->user, 'admin'))->toBeTrue()
        ->and($this->team->memberHasPermission($this->user, 'anything'))->toBeTrue()
        ->and($this->team->memberHasRole($this->user, 'reviewer'))->toBeFalse();
});

it('keeps a grant inside its own team', function (): void {
    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');
    Permissions::for($this->user)->in($this->team)->assignRole('reviewer');

    expect($this->other->memberHasPermission($this->user, 'posts.edit'))->toBeFalse()
        ->and($this->other->memberHasRole($this->user, 'reviewer'))->toBeFalse()
        ->and($this->user->can('teams.posts.edit', $this->other))->toBeFalse();
});

it('never lets a team grant satisfy an unscoped check', function (): void {
    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');

    expect($this->user->hasPermissionTo('posts.edit'))->toBeFalse()
        ->and($this->user->can('posts.edit'))->toBeFalse();
});

it('reads the membership at check time: an expired membership grants nothing', function (): void {
    $this->travelTo(now()->startOfSecond());
    $this->team->findMember($this->user)?->update(['expires_at' => now()->addHour()]);
    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');

    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeTrue();

    $this->travel(2)->hours();

    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeFalse()
        ->and($this->team->memberHasRole($this->user, 'reviewer'))->toBeFalse();
});

it('reads the membership at check time: a membership that has not started grants nothing', function (): void {
    config()->set('teams.members.start_dates', true);
    $this->travelTo(now()->startOfSecond());
    $this->team->findMember($this->user)?->update(['starts_at' => now()->addDay()]);
    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');

    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeFalse();

    $this->travel(2)->days();

    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeTrue();
});

it('ignores the leftover grant of a removed member even without the cleanup', function (): void {
    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');

    // Removed behind the API's back: no event, so the grant row stays.
    $this->team->findMember($this->user)?->delete();

    expect(Permissions::for($this->user)->in($this->team)->hasPermissionTo('posts.edit'))->toBeTrue()
        ->and($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeFalse();
});

it('gives a non-member nothing, whatever permissions holds for the team', function (): void {
    $stranger = RoleUser::create();
    Permissions::for($stranger)->in($this->team)->givePermissionTo('posts.edit');

    expect($this->team->memberHasPermission($stranger, 'posts.edit'))->toBeFalse()
        ->and(PermissionsBridge::scopesFor($this->team, $stranger))->toBe([]);
});

it('counts a global grant in every team the holder is an active member of', function (): void {
    Permissions::for($this->user)->givePermissionTo('posts.edit');
    $notMine = Team::factory()->create();

    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeTrue()
        ->and($notMine->memberHasPermission($this->user, 'posts.edit'))->toBeFalse();
});

it('honours permissions.assignments.global_applies_in_scope = false', function (): void {
    config()->set('permissions.assignments.global_applies_in_scope', false);
    Permissions::for($this->user)->givePermissionTo('posts.edit');

    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeFalse();
});

it('leaves a member that cannot hold grants on the membership role alone', function (): void {
    $plain = User::create();
    Teams::for($this->team)->members()->add($plain, 'user');

    expect($this->team->memberHasPermission($plain, 'posts.edit'))->toBeFalse()
        ->and($this->team->memberHasRole($plain, 'reviewer'))->toBeFalse()
        ->and(PermissionsBridge::holdsGrants($plain))->toBeFalse()
        ->and(PermissionsBridge::holdsGrants(RoleUser::class))->toBeTrue();
});

it('has no scopes for an unsaved team', function (): void {
    expect(PermissionsBridge::scopesFor(Team::factory()->make(), $this->user))->toBe([]);
});

describe('with roles inheriting down', function (): void {
    beforeEach(function (): void {
        config()->set('teams.nesting.enabled', true);
        config()->set('teams.nesting.inherit_roles_down', true);

        $this->company = Team::factory()->create();
        $this->squad = Team::factory()->create();
        Teams::for($this->squad)->moveUnder($this->company);
        $this->squad = $this->squad->fresh();
    });

    it('counts a grant held in a team above, for a member of that team', function (): void {
        Teams::for($this->company)->members()->add($this->user, 'user');
        Permissions::for($this->user)->in($this->company)->givePermissionTo('posts.edit');

        expect($this->squad->memberHasPermission($this->user, 'posts.edit'))->toBeTrue()
            ->and(array_map(static fn (Team $team): mixed => $team->getKey(), PermissionsBridge::scopesFor($this->squad, $this->user)))
            ->toBe([$this->company->getKey()]);
    });

    it('ignores a grant in a team above the member does not belong to', function (): void {
        Teams::for($this->squad)->members()->add($this->user, 'user');
        Permissions::for($this->user)->in($this->company)->givePermissionTo('posts.edit');

        expect($this->squad->memberHasPermission($this->user, 'posts.edit'))->toBeFalse();
    });

    it('never pushes a grant up from a team below', function (): void {
        Teams::for($this->company)->members()->add($this->user, 'user');
        Teams::for($this->squad)->members()->add($this->user, 'user');
        Permissions::for($this->user)->in($this->squad)->givePermissionTo('posts.edit');

        expect($this->company->memberHasPermission($this->user, 'posts.edit'))->toBeFalse()
            ->and($this->squad->memberHasPermission($this->user, 'posts.edit'))->toBeTrue();
    });

    it('stops counting the grant once the membership above expires', function (): void {
        Teams::for($this->company)->members()->add($this->user, 'user', expiresAt: now()->addHour());
        Permissions::for($this->user)->in($this->company)->givePermissionTo('posts.edit');

        $this->travel(2)->hours();

        expect($this->squad->memberHasPermission($this->user, 'posts.edit'))->toBeFalse();
    });
});
