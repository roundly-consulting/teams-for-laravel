<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Permissions\Facades\Permissions;
use RoundlyConsulting\Teams\Bridges\Permissions\PermissionsBridge;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Permissions\RoleUser;

beforeEach(function (): void {
    Permissions::permission('posts.edit');

    $this->team = Team::factory()->create();
    $this->other = Team::factory()->create();
    $this->user = RoleUser::create();

    Teams::for($this->team)->members()->add($this->user, 'user');
    Teams::for($this->other)->members()->add($this->user, 'user');
    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');
});

it('answers a scope-less Gate check in the current team', function (): void {
    expect($this->user->can('posts.edit'))->toBeFalse();

    Teams::switchTo($this->team, $this->user);

    expect($this->user->can('posts.edit'))->toBeTrue();
});

it('does not carry one team\'s grant into another team\'s context', function (): void {
    Teams::switchTo($this->other, $this->user);

    expect($this->user->can('posts.edit'))->toBeFalse();
});

it('gives no scope to a non-member, even in an unchecked context', function (): void {
    $stranger = RoleUser::create();
    Permissions::for($stranger)->in($this->team)->givePermissionTo('posts.edit');
    Teams::setCurrent($this->team);

    expect($stranger->can('posts.edit'))->toBeFalse()
        ->and(PermissionsBridge::ambientScopes($stranger))->toBeNull();
});

it('gives no scope once the membership has expired', function (): void {
    Teams::switchTo($this->team, $this->user);
    $this->team->findMember($this->user)?->update(['expires_at' => now()->subMinute()]);

    expect($this->user->can('posts.edit'))->toBeFalse();
});

it('gives no scope in multi-team mode', function (): void {
    Teams::setCurrentMany([$this->team, $this->other], $this->user);

    expect(PermissionsBridge::ambientScopes($this->user))->toBeNull()
        ->and($this->user->can('posts.edit'))->toBeFalse();
});

it('scopes `can:` middleware under the teams.context middleware', function (): void {
    Route::middleware('teams.context')
        ->get('/teams/{team}/edit', static fn (): string => 'edited')
        ->middleware('can:posts.edit');

    $this->actingAs($this->user)->get('/teams/'.$this->team->getKey().'/edit')->assertOk()->assertSee('edited');
    $this->actingAs($this->user)->get('/teams/'.$this->other->getKey().'/edit')->assertForbidden();
});

it('includes the teams above the current one when roles inherit down', function (): void {
    config()->set('teams.nesting.enabled', true);
    config()->set('teams.nesting.inherit_roles_down', true);

    $squad = Team::factory()->create();
    Teams::for($squad)->moveUnder($this->team);
    Teams::setCurrent($squad->fresh());

    expect(array_map(static fn (Team $team): mixed => $team->getKey(), PermissionsBridge::ambientScopes($this->user) ?? []))
        ->toBe([$this->team->getKey()])
        ->and($this->user->can('posts.edit'))->toBeTrue();
});

it('lets the host replace the resolver', function (): void {
    Teams::switchTo($this->team, $this->user);
    Permissions::resolveScopeUsing(null);

    expect($this->user->can('posts.edit'))->toBeFalse();
});
