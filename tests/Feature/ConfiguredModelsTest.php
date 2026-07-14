<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Support\JoinRequestModel;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomInvite;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomJoinRequest;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomMember;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeam;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeamRole;
use RoundlyConsulting\Teams\Tests\User;

/**
 * `config/teams.php` invites a host to swap all five models for its own subclass.
 * Every one of Team's `hasMany` relations therefore has to name its foreign key:
 * Eloquent derives an unnamed one from the PARENT'S CLASS NAME, so a configured
 * `CustomTeam` silently looks for `custom_team_id` — a column no table has, which
 * breaks every member, invite, role-override and join-request read and write.
 *
 * These tests drive the real flows through the configured subclasses. The default
 * path is identical either way, which is why 301 green tests never noticed.
 */
beforeEach(function (): void {
    config()->set('teams.models.team', CustomTeam::class);
    config()->set('teams.models.member', CustomMember::class);
    config()->set('teams.models.invite', CustomInvite::class);
    config()->set('teams.models.team_role', CustomTeamRole::class);
    config()->set('teams.models.join_request', CustomJoinRequest::class);

    $this->team = TeamModel::query()->create(['name' => 'Acme']);
});

it('names the foreign key on every relation a configured team owns', function (): void {
    expect($this->team->members()->getForeignKeyName())->toBe('team_id')
        ->and($this->team->invites()->getForeignKeyName())->toBe('team_id')
        ->and($this->team->teamRoles()->getForeignKeyName())->toBe('team_id')
        ->and($this->team->joinRequests()->getForeignKeyName())->toBe('team_id');
});

it('adds and reads members through the configured models', function (): void {
    $user = User::create();

    $member = $this->team->addMember($user, 'admin');

    expect($member)->toBeInstanceOf(CustomMember::class)
        ->and($this->team->hasMember($user))->toBeTrue()
        ->and($this->team->findMember($user)?->getKey())->toBe($member->getKey())
        ->and($this->team->members()->count())->toBe(1)
        ->and($this->team->memberHasRole($user, 'admin'))->toBeTrue()
        ->and($this->team->memberHasPermission($user, 'anything'))->toBeTrue();
});

it('issues and accepts invites through the configured models', function (): void {
    $invite = $this->team->invite(now()->addWeek(), 'user');

    expect($invite)->toBeInstanceOf(CustomInvite::class)
        ->and($this->team->invites()->count())->toBe(1);

    $member = $invite->acceptBy(User::create());

    expect($member)->toBeInstanceOf(CustomMember::class)
        ->and($member->accepted_invite_id)->toBe($invite->getKey())
        ->and($invite->refresh()->acceptedMembers()->count())->toBe(1);
});

it('defines per-team role overrides through the configured models', function (): void {
    config()->set('teams.roles.per_team', true);

    $role = $this->team->defineRole('lead', 'Lead', ['posts.publish']);

    expect($role)->toBeInstanceOf(CustomTeamRole::class)
        ->and($this->team->teamRoles()->count())->toBe(1)
        ->and($this->team->roles())->toHaveKey('lead');

    $user = User::create();
    $this->team->addMember($user, 'lead');

    expect($this->team->memberHasPermission($user, 'posts.publish'))->toBeTrue();
});

it('opens and approves join requests through the configured models', function (): void {
    $requester = User::create();
    $responder = User::create();

    $request = app('teams')->requestToJoin($this->team, $requester, 'user');

    expect($request)->toBeInstanceOf(CustomJoinRequest::class)
        ->and($this->team->joinRequests()->count())->toBe(1)
        ->and(JoinRequestModel::query()->pending()->count())->toBe(1);

    app('teams')->for($this->team)->approveJoinRequest($request, $responder);

    expect($this->team->hasMember($requester))->toBeTrue();
});
