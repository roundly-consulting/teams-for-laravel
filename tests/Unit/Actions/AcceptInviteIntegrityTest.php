<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Events\InviteAccepted;
use RoundlyConsulting\Teams\Exceptions\InviteExhaustedException;
use RoundlyConsulting\Teams\Exceptions\InviteNotFoundException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

it('admits exactly one person through a single-use invite, even from a stale copy', function (): void {
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'admin');
    $stale = Invite::query()->findOrFail($invite->getKey());
    $first = User::create();
    $second = User::create();

    Teams::invites()->accept($invite, $first);

    expect(fn () => Teams::invites()->accept($stale, $second))
        ->toThrow(InviteExhaustedException::class);

    expect($team->hasMember($first))->toBeTrue()
        ->and($team->hasMember($second))->toBeFalse();
});

it('never admits more people than max_uses when copies of a link race', function (): void {
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'user', maxUses: 2);
    $copies = [
        Invite::query()->findOrFail($invite->getKey()),
        Invite::query()->findOrFail($invite->getKey()),
        Invite::query()->findOrFail($invite->getKey()),
    ];

    Teams::invites()->accept($copies[0], User::create());
    Teams::invites()->accept($copies[1], User::create());

    expect(fn () => Teams::invites()->accept($copies[2], User::create()))
        ->toThrow(InviteExhaustedException::class);

    expect($team->members()->count())->toBe(2)
        ->and(Invite::withTrashed()->findOrFail($invite->getKey())->uses)->toBe(2);
});

it('refuses an invite that was revoked after it was loaded', function (): void {
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'admin', maxUses: 5);
    $stale = Invite::query()->findOrFail($invite->getKey());
    $user = User::create();

    Teams::for($team)->invites()->revoke($invite);

    expect(fn () => Teams::invites()->accept($stale, $user))
        ->toThrow(InviteNotFoundException::class);

    expect($team->hasMember($user))->toBeFalse();
});

it('claims the seat with a conditional update, so a seat taken meanwhile is never handed out twice', function (): void {
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'admin', maxUses: 2);
    $user = User::create();

    // Another process takes the last seat right after this one read the invite row.
    $fired = false;
    DB::listen(function (QueryExecuted $query) use (&$fired, $invite): void {
        if ($fired || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'team_invites')) {
            return;
        }

        $fired = true;
        DB::table('team_invites')->where('id', $invite->getKey())->update(['uses' => 2]);
    });

    expect(fn () => Teams::invites()->accept($invite, $user))
        ->toThrow(InviteExhaustedException::class);

    // (The competitor writes on this test's own connection, so the refusal's rollback
    // undoes its update too — only the refusal and the absent membership are observable.)
    expect($fired)->toBeTrue()
        ->and($team->hasMember($user))->toBeFalse();
});

it('re-reads the invite under a row lock inside the transaction', function (): void {
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'admin');

    LockRecorder::flush();

    if (DriverMatrix::driver() === 'sqlite') {
        $connection = DB::connection();
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));
        LockRecorder::listenForMarkers();
    } else {
        DB::listen(static function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                LockRecorder::record('lock-for-update', $query->connection->transactionLevel(), $query->sql);
            }
        });
    }

    Teams::invites()->accept($invite, User::create());

    $locks = LockRecorder::recorded();

    expect($locks)->not->toBeEmpty()
        ->and($locks[0]['sql'])->toContain('team_invites')
        ->and($locks[0]['transactionDepth'])->toBeGreaterThanOrEqual(1);
});

it('does not burn a seat when an existing member accepts the same link again', function (): void {
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'user', maxUses: 3);
    $user = User::create();

    Teams::invites()->accept($invite, $user);
    Teams::invites()->accept($invite, $user);
    Teams::invites()->accept($invite, $user);

    $fresh = Invite::query()->findOrFail($invite->getKey());

    expect($team->members()->count())->toBe(1)
        ->and($fresh->uses)->toBe(1)
        ->and($fresh->consumedSeats())->toBe($fresh->acceptedMembers()->count())
        ->and($fresh->remainingSeats())->toBe(2);
});

it('never downgrades an existing member who accepts an invite', function (): void {
    Teams::roles()->register('owner', 'Owner', ['*']);
    Teams::roles()->register('member', 'Member', ['posts.read']);
    $owner = User::create();
    $team = Teams::create(new CreateTeamData(name: 'Acme', owner: $owner));
    $invite = Teams::for($team)->invites()->create(role: 'member');

    Event::fake(InviteAccepted::class);

    $member = Teams::invites()->accept($invite, $owner);

    expect($member->role)->toBe('owner')
        ->and($team->memberHasPermission($owner, 'members.manage'))->toBeTrue()
        ->and(Invite::query()->findOrFail($invite->getKey())->uses)->toBe(0);

    Event::assertNotDispatched(InviteAccepted::class);
});
