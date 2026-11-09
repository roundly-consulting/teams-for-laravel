<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Permissions\Facades\Permissions;
use RoundlyConsulting\Teams\Bridges\Permissions\PermissionsBridge;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Permissions\RoleUser;

beforeEach(function (): void {
    Permissions::permission('posts.edit');

    $this->team = Team::factory()->create();
    $this->user = RoleUser::create();
    Teams::for($this->team)->members()->add($this->user, 'user');
    Permissions::for($this->user)->in($this->team)->givePermissionTo('posts.edit');
});

it('is off although permissions is installed', function (): void {
    expect(PermissionsBridge::installed())->toBeTrue()
        ->and(PermissionsBridge::enabled())->toBeFalse();

    Artisan::call('about', ['--only' => 'teams']);

    expect(Artisan::output())->toMatch('/Permissions bridge\s*\.+\s*OFF/');
});

it('keeps the team checks on the membership role alone', function (): void {
    expect($this->team->memberHasPermission($this->user, 'posts.edit'))->toBeFalse()
        ->and($this->user->can('teams.posts.edit', $this->team))->toBeFalse();
});

it('installs no ambient scope', function (): void {
    Teams::switchTo($this->team, $this->user);

    expect($this->user->can('posts.edit'))->toBeFalse();
});

it('refuses member grants', function (): void {
    expect(fn () => Teams::for($this->team)->members()->grant($this->user))
        ->toThrow(TeamsException::class, 'turn on teams.permissions.bridge');
});

it('makes no permissions call on removal or team deletion', function (): void {
    $fake = Permissions::fake();

    Teams::for($this->team)->members()->remove($this->user);
    $this->team->forceDelete();

    $fake->assertNoAuthorizationForgotten();
    $fake->assertNoScopeForgotten();
    $fake->assertNoRoleAssigned();
    $fake->assertNoPermissionGranted();
});
