<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Connections\PendingConnection;
use RoundlyConsulting\Contacts\ContactBook;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Enums\JoinPolicy;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\InviteResent;
use RoundlyConsulting\Teams\Events\InviteRevoked;
use RoundlyConsulting\Teams\Events\JoinRequestExpired;
use RoundlyConsulting\Teams\Events\MembershipExpired;
use RoundlyConsulting\Teams\Events\MembershipExpiringSoon;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Exceptions\InviteExpiredException;
use RoundlyConsulting\Teams\Exceptions\InviteNotFoundException;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotFoundException;
use RoundlyConsulting\Teams\Exceptions\MemberNotFoundException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Handles\TeamHandle;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Settings\TeamSettings;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Tests\User;

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Teams::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

function facadePendingRequest(Team $team, User $user, array $attributes = []): JoinRequest
{
    return JoinRequest::factory()->for($team)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'status' => JoinRequestStatus::Pending,
        ...$attributes,
    ]);
}

describe('the manager', function (): void {
    it('is a container singleton behind the facade', function (): void {
        expect(app(TeamsManager::class))->toBe(app(TeamsManager::class))
            ->and(Teams::getFacadeRoot())->toBe(app(TeamsManager::class));
    });

    it('works injected, without the facade', function (): void {
        $teams = app(TeamsManager::class);
        $owner = User::create();
        $user = User::create();

        $team = $teams->create(new CreateTeamData(name: 'Acme', owner: $owner));
        $member = $teams->for($team)->members()->add($user, 'admin');

        expect($member->role)->toBe('admin')
            ->and($team->isOwnedBy($owner))->toBeTrue()
            ->and($teams->for($team)->members()->has($user))->toBeTrue();
    });

    it('runs the raw action the same way', function (): void {
        $team = Team::factory()->create();
        $user = User::create();

        $member = app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'user'));

        expect($member->role)->toBe('user')
            ->and(Teams::for($team)->members()->has($user))->toBeTrue();
    });
});

describe('Teams::create()', function (): void {
    it('creates a team and seats the owner', function (): void {
        $owner = User::create();

        $team = Teams::create(new CreateTeamData(name: 'Acme', isPublic: true, owner: $owner));

        expect($team)->toBeInstanceOf(Team::class)
            ->and($team->name)->toBe('Acme')
            ->and($team->is_public)->toBeTrue()
            ->and($team->memberHasRole($owner, 'owner'))->toBeTrue();
    });
});

describe('Teams::for($team)', function (): void {
    it('returns a handle bound to the team', function (): void {
        $team = Team::factory()->create();

        $handle = Teams::for($team);

        expect($handle)->toBeInstanceOf(TeamHandle::class)
            ->and($handle->team())->toBe($team)
            ->and($handle->settings())->toBeInstanceOf(TeamSettings::class)
            ->and($handle->contacts())->toBeInstanceOf(ContactBook::class)
            ->and($handle->addresses())->toBeInstanceOf(AddressBook::class)
            ->and($handle->connections())->toBeInstanceOf(PendingConnection::class);
    });

    it('transfers ownership and returns the team', function (): void {
        $old = User::create();
        $new = User::create();
        $team = Teams::create(new CreateTeamData(name: 'Acme', owner: $old));

        $result = Teams::for($team)->transferOwnershipTo($new);

        expect($result)->toBe($team)
            ->and($team->fresh()->isOwnedBy($new))->toBeTrue()
            ->and($team->memberHasRole($old, 'admin'))->toBeTrue();
    });
});

describe('Teams::for($team)->members()', function (): void {
    it('adds, reads, changes and removes members', function (): void {
        $team = Team::factory()->create();
        $user = User::create();
        $members = Teams::for($team)->members();

        $member = $members->add($user, 'user', ['source' => 'api'], now()->addMonth());

        expect($member)->toBeInstanceOf(Member::class)
            ->and($member->meta->get('source'))->toBe('api')
            ->and($member->expires_at)->not->toBeNull()
            ->and($members->has($user))->toBeTrue()
            ->and($members->find($user)?->is($member))->toBeTrue()
            ->and($members->all()->pluck('id')->all())->toBe([$member->getKey()]);

        $changed = $members->changeRole($user, 'admin');

        expect($changed)->toBeInstanceOf(Member::class)
            ->and($changed->role)->toBe('admin');

        expect($members->remove($user))->toBeTrue()
            ->and($members->has($user))->toBeFalse()
            ->and($members->remove($user))->toBeFalse();
    });

    it('refuses to change the role of a non-member instead of silently doing nothing', function (): void {
        Event::fake([TeamMemberRoleChanged::class]);

        $team = Team::factory()->create();
        $stranger = User::create();

        expect(fn () => Teams::for($team)->members()->changeRole($stranger, 'admin'))
            ->toThrow(MemberNotFoundException::class);

        expect($team->hasMember($stranger))->toBeFalse();
        Event::assertNotDispatched(TeamMemberRoleChanged::class);
    });

    it('refuses to change the role of another team\'s member', function (): void {
        $mine = Team::factory()->create();
        $theirs = Team::factory()->create();
        $user = User::create();
        $theirs->addMember($user, 'user');

        expect(fn () => Teams::for($mine)->members()->changeRole($user, 'admin'))
            ->toThrow(MemberNotFoundException::class);

        expect($theirs->memberHasRole($user, 'user'))->toBeTrue();
    });
});

describe('Teams::for($team)->invites()', function (): void {
    it('creates an invite with role first, then the options', function (): void {
        $team = Team::factory()->create();
        $inviter = User::create();

        $invite = Teams::for($team)->invites()->create(
            role: 'admin',
            expiresAt: now()->addDays(3),
            email: 'jane@acme.test',
            invitedBy: $inviter,
            meta: ['source' => 'ui'],
            maxUses: 5,
        );

        expect($invite)->toBeInstanceOf(Invite::class)
            ->and($invite->role)->toBe('admin')
            ->and($invite->email)->toBe('jane@acme.test')
            ->and($invite->max_uses)->toBe(5)
            ->and($invite->invitedBy?->is($inviter))->toBeTrue()
            ->and($invite->meta->get('source'))->toBe('ui');
    });

    it('lists pending invites only', function (): void {
        $team = Team::factory()->create();
        $live = Teams::for($team)->invites()->create('user');
        Invite::factory()->for($team)->expired()->create();
        Invite::factory()->for(Team::factory())->create();

        expect(Teams::for($team)->invites()->pending()->pluck('id')->all())->toBe([$live->getKey()]);
    });

    it('resends and revokes its own invites', function (): void {
        Event::fake([InviteResent::class, InviteRevoked::class]);

        $team = Team::factory()->create();
        $invite = Invite::factory()->for($team)->expired()->create(['code' => 'before']);

        $resent = Teams::for($team)->invites()->resend($invite);

        expect($resent->code)->not->toBe('before')
            ->and($resent->isExpired())->toBeFalse()
            ->and(Teams::for($team)->invites()->revoke($invite))->toBeTrue()
            ->and($invite->fresh()->trashed())->toBeTrue();

        Event::assertDispatched(InviteResent::class, 1);
        Event::assertDispatched(InviteRevoked::class, 1);
    });

    it('refuses to resend another team\'s invite', function (): void {
        Event::fake([InviteResent::class]);

        $mine = Team::factory()->create();
        $theirs = Team::factory()->create();
        $invite = Invite::factory()->for($theirs)->create(['code' => 'theirs-code']);

        expect(fn () => Teams::for($mine)->invites()->resend($invite))
            ->toThrow(InviteNotFoundException::class, 'Invite #'.$invite->getKey().' does not belong to this team.');

        expect($invite->fresh()->code)->toBe('theirs-code');
        Event::assertNotDispatched(InviteResent::class);
    });

    it('refuses to revoke another team\'s invite', function (): void {
        Event::fake([InviteRevoked::class]);

        $mine = Team::factory()->create();
        $theirs = Team::factory()->create();
        $invite = Invite::factory()->for($theirs)->create();

        expect(fn () => Teams::for($mine)->invites()->revoke($invite))
            ->toThrow(InviteNotFoundException::class);

        expect($invite->fresh()->trashed())->toBeFalse();
        Event::assertNotDispatched(InviteRevoked::class);
    });
});

describe('Teams::for($team)->joinRequests()', function (): void {
    it('opens, lists, approves and denies requests', function (): void {
        $team = Team::factory()->create();
        $alice = User::create();
        $bob = User::create();
        $admin = User::create();
        $requests = Teams::for($team)->joinRequests();

        $first = $requests->open($alice, requestedRole: 'admin', message: 'Hi', meta: ['via' => 'web'], expiresAt: now()->addWeek());
        $second = $requests->open($bob);

        expect($first->status)->toBe(JoinRequestStatus::Pending)
            ->and($first->message)->toBe('Hi')
            ->and($first->expires_at)->not->toBeNull()
            ->and($requests->pending()->pluck('id')->sort()->values()->all())->toBe([$first->getKey(), $second->getKey()]);

        $member = $requests->approve($first, by: $admin);
        $denied = $requests->deny($second, by: $admin);

        expect($member->role)->toBe('admin')
            ->and($first->fresh()->status)->toBe(JoinRequestStatus::Approved)
            ->and($denied->status)->toBe(JoinRequestStatus::Denied)
            ->and($requests->pending())->toBeEmpty();
    });

    it('approves with an explicit role override', function (): void {
        $team = Team::factory()->create();
        $user = User::create();
        $request = facadePendingRequest($team, $user, ['requested_role' => 'admin']);

        $member = Teams::for($team)->joinRequests()->approve($request, by: User::create(), role: 'user');

        expect($member->role)->toBe('user');
    });

    it('refuses to approve another team\'s join request', function (): void {
        $mine = Team::factory()->create();
        $theirs = Team::factory()->create();
        $requester = User::create();
        $request = facadePendingRequest($theirs, $requester, ['requested_role' => 'admin']);

        expect(fn () => Teams::for($mine)->joinRequests()->approve($request, by: User::create()))
            ->toThrow(JoinRequestNotFoundException::class, 'Join request #'.$request->getKey().' does not belong to this team.');

        expect($request->fresh()->status)->toBe(JoinRequestStatus::Pending)
            ->and($theirs->hasMember($requester))->toBeFalse()
            ->and($mine->hasMember($requester))->toBeFalse();
    });

    it('refuses to deny another team\'s join request', function (): void {
        $mine = Team::factory()->create();
        $theirs = Team::factory()->create();
        $request = facadePendingRequest($theirs, User::create());

        expect(fn () => Teams::for($mine)->joinRequests()->deny($request, by: User::create()))
            ->toThrow(JoinRequestNotFoundException::class);

        expect($request->fresh()->status)->toBe(JoinRequestStatus::Pending);
    });

    it('stages approvers immutably', function (): void {
        $team = Team::factory()->create();
        $requests = Teams::for($team)->joinRequests();

        $staged = $requests->requireApprovalFrom(User::create());

        expect($staged)->not->toBe($requests)
            ->and($staged->rule(ApprovalRule::Any))->not->toBe($staged)
            ->and($staged->quorum(2))->not->toBe($staged);
    });

    it('auto-approves on an open team', function (): void {
        $team = Team::factory()->create();
        $user = User::create();
        Teams::for($team)->settings()->setJoinPolicy(JoinPolicy::Open);

        $request = Teams::for($team)->joinRequests()->open($user);

        expect($request->status)->toBe(JoinRequestStatus::Approved)
            ->and($team->hasMember($user))->toBeTrue();
    });
});

describe('Teams::for($team)->roles()', function (): void {
    it('defines per-team overrides and reads the effective roles', function (): void {
        config()->set('teams.roles.per_team', true);
        $team = Team::factory()->create();

        $override = Teams::for($team)->roles()->define('editor', 'Editor', ['posts.publish', new Permission('posts.edit')], 'Editors');

        expect($override)->toBeInstanceOf(TeamRole::class)
            ->and($override->permissions->all())->toBe(['posts.publish', 'posts.edit'])
            ->and(Teams::for($team)->roles()->all())->toHaveKeys(['admin', 'user', 'editor'])
            ->and(Teams::for($team)->roles()->find('editor'))->toBeInstanceOf(Role::class)
            ->and(Teams::for($team)->roles()->find('editor')?->name)->toBe('Editor')
            ->and(Teams::for($team)->roles()->find('missing'))->toBeNull()
            ->and(Teams::for(Team::factory()->create())->roles()->all())->not->toHaveKey('editor');
    });
});

describe('Teams::invites()', function (): void {
    it('accepts an invite model', function (): void {
        $team = Team::factory()->create();
        $user = User::create();
        $invite = Invite::factory()->for($team)->create(['role' => 'admin', 'email' => 'jane@acme.test']);

        $member = Teams::invites()->accept($invite, $user, email: 'jane@acme.test');

        expect($member->role)->toBe('admin')
            ->and($team->hasMember($user))->toBeTrue();
    });

    it('accepts an invite by code', function (): void {
        $team = Team::factory()->create();
        $user = User::create();
        Invite::factory()->for($team)->create(['code' => 'join-me', 'role' => 'admin']);

        $member = Teams::invites()->accept('join-me', $user);

        expect($member->role)->toBe('admin')
            ->and($team->hasMember($user))->toBeTrue();
    });

    it('throws for an unknown code', function (): void {
        expect(fn () => Teams::invites()->accept('missing', User::create()))
            ->toThrow(InviteNotFoundException::class, 'No invite was found for the code "missing".');
    });

    it('propagates expiry when accepting by code', function (): void {
        $team = Team::factory()->create();
        Invite::factory()->for($team)->expired()->create(['code' => 'stale']);

        expect(fn () => Teams::invites()->accept('stale', User::create()))
            ->toThrow(InviteExpiredException::class);
    });

    it('finds an invite by code', function (): void {
        $invite = Invite::factory()->for(Team::factory())->create(['code' => 'find-me']);

        expect(Teams::invites()->find('find-me')?->is($invite))->toBeTrue()
            ->and(Teams::invites()->find('nope'))->toBeNull();
    });

    it('prunes long-expired invites', function (): void {
        $team = Team::factory()->create();
        Invite::factory()->for($team)->create(['expires_at' => now()->subMonths(2)]);
        $fresh = Invite::factory()->for($team)->create();

        expect(Teams::invites()->prune())->toBe(1)
            ->and(Invite::withTrashed()->pluck('id')->all())->toBe([$fresh->getKey()]);
    });
});

describe('Teams::members()', function (): void {
    it('reports expiring memberships without notifying', function (): void {
        Event::fake([MembershipExpiringSoon::class]);
        config()->set('teams.members.expiring_within', 3);

        $team = Team::factory()->create();
        $soon = $team->addMember(User::create(), 'user', expiresAt: now()->addDays(2));
        $team->addMember(User::create(), 'user', expiresAt: now()->addDays(10));

        expect(Teams::members()->expiring()->pluck('id')->all())->toBe([$soon->getKey()])
            ->and(Teams::members()->expiring(30))->toHaveCount(2);

        Event::assertNotDispatched(MembershipExpiringSoon::class);
    });

    it('notifies expiring memberships', function (): void {
        Event::fake([MembershipExpiringSoon::class]);

        $team = Team::factory()->create();
        $team->addMember(User::create(), 'user', expiresAt: now()->addDays(2));

        expect(Teams::members()->notifyExpiring(7))->toHaveCount(1);

        Event::assertDispatched(MembershipExpiringSoon::class, 1);
    });

    it('prunes long-expired memberships', function (): void {
        Event::fake([MembershipExpired::class]);

        $team = Team::factory()->create();
        $team->addMember(User::create(), 'user', expiresAt: now()->subMonths(3));
        $team->addMember(User::create(), 'user');

        expect(Teams::members()->prune())->toBe(1)
            ->and($team->members()->count())->toBe(1);

        Event::assertDispatched(MembershipExpired::class, 1);
    });
});

describe('Teams::joinRequests()', function (): void {
    it('auto-declines expired pending requests', function (): void {
        Event::fake([JoinRequestExpired::class]);

        $team = Team::factory()->create();
        $stale = facadePendingRequest($team, User::create(), ['expires_at' => now()->subDay()]);
        $live = facadePendingRequest($team, User::create(), ['expires_at' => now()->addDay()]);

        expect(Teams::joinRequests()->expire())->toBe(1)
            ->and($stale->fresh()->status)->toBe(JoinRequestStatus::Denied)
            ->and($live->fresh()->status)->toBe(JoinRequestStatus::Pending);

        Event::assertDispatched(JoinRequestExpired::class, 1);
    });
});

describe('Teams::roles() and Teams::permissions()', function (): void {
    it('registers, finds and lists global roles', function (): void {
        $role = Teams::roles()->register('auditor', 'Auditor', ['reports.view']);

        expect($role)->toBeInstanceOf(Role::class)
            ->and(Teams::roles()->find('auditor'))->toBe($role)
            ->and(Teams::roles()->find('nope'))->toBeNull()
            ->and(Teams::roles()->all())->toHaveKeys(['admin', 'user', 'auditor']);
    });

    it('registers, groups, finds and harvests permissions', function (): void {
        Teams::roles()->register('auditor', 'Auditor', ['reports.view']);

        $permission = Teams::permissions()->register('posts.publish', 'Publish posts');
        Teams::permissions()->group('Content', ['posts.publish']);

        expect($permission)->toBeInstanceOf(Permission::class)
            ->and(Teams::permissions()->find('posts.publish')?->group)->toBe('Content')
            ->and(Teams::permissions()->all())->toHaveKey('posts.publish')
            ->and(Teams::permissions()->fromRoles())->toHaveKeys(['posts.publish', 'reports.view']);
    });
});

describe('model methods go through the manager', function (): void {
    it('Team::invite() takes the role first, like the handle', function (): void {
        $team = Team::factory()->create();
        $expires = now()->addDays(2)->startOfSecond();

        $invite = $team->invite('admin', $expires, email: 'jo@acme.test', maxUses: null);

        expect($invite->role)->toBe('admin')
            ->and($invite->expires_at->equalTo($expires))->toBeTrue()
            ->and($invite->email)->toBe('jo@acme.test')
            ->and($invite->max_uses)->toBeNull();
    });

    it('Team::roles()/defineRole()/addMember()/removeMember()', function (): void {
        config()->set('teams.roles.per_team', true);
        $team = Team::factory()->create();
        $user = User::create();

        $team->defineRole('editor', 'Editor', ['posts.edit']);
        $team->addMember($user, 'editor');

        expect($team->roles())->toHaveKey('editor')
            ->and($team->memberHasPermission($user, 'posts.edit'))->toBeTrue()
            ->and($team->removeMember($user))->toBeTrue()
            ->and($team->hasMember($user))->toBeFalse();
    });

    it('Invite::acceptBy()/resend()/revoke()', function (): void {
        $team = Team::factory()->create();
        $user = User::create();
        $invite = Invite::factory()->for($team)->create(['code' => 'old', 'max_uses' => null]);

        expect($invite->resend()->code)->not->toBe('old')
            ->and($invite->acceptBy($user)->team_id)->toBe($team->getKey())
            ->and($invite->revoke())->toBeTrue();
    });

    it('Member::removeFromTeam()', function (): void {
        $team = Team::factory()->create();
        $user = User::create();
        $membership = $team->addMember($user, 'user');

        expect($membership->removeFromTeam())->toBeTrue()
            ->and($team->hasMember($user))->toBeFalse();
    });
});
