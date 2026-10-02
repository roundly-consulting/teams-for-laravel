<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

it('allows one membership row per team and member at the database level', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'user');

    expect(fn () => Member::query()->insert([
        'team_id' => $team->getKey(),
        'member_type' => $user->getMorphClass(),
        'member_id' => $user->getKey(),
        'role' => 'admin',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('returns the winner when a concurrent add inserts the same membership first', function (): void {
    $team = Team::factory()->create();
    $user = User::create();

    // Another process commits the same membership right after our lookup came back
    // empty — the window a double-submit or two simultaneous accepts land in.
    $fired = false;
    DB::listen(function (QueryExecuted $query) use (&$fired, $team, $user): void {
        if ($fired || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'team_members')) {
            return;
        }

        $fired = true;
        DB::table('team_members')->insert([
            'team_id' => $team->getKey(),
            'member_type' => $user->getMorphClass(),
            'member_id' => $user->getKey(),
            'role' => 'user',
        ]);
    });

    $member = Teams::for($team)->members()->add($user, 'user');

    expect($fired)->toBeTrue()
        ->and(Member::withTrashed()->where('team_id', $team->getKey())->count())->toBe(1)
        ->and($team->findMember($user)?->is($member))->toBeTrue();

    Teams::for($team)->members()->remove($user);

    expect($team->hasMember($user))->toBeFalse()
        ->and($user->can('teams.anything', $team))->toBeFalse();
});

it('locks the team row and reads memberships with locks inside one transaction', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()->setMaxSeats(5);

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

    $team->addMember(User::create(), 'user');

    $locks = LockRecorder::recorded();

    // The team row first (serialises adds), then the member lookup and the seat count.
    expect($locks)->toHaveCount(3)
        ->and($locks[0]['sql'])->toContain('"teams"')
        ->and($locks[1]['sql'])->toContain('team_members')
        ->and($locks[2]['sql'])->toContain('team_members')
        ->and(array_column($locks, 'transactionDepth'))->each->toBeGreaterThanOrEqual(1);
});

it('restores a removed member\'s row instead of inserting a second one', function (): void {
    Event::fake(TeamMemberAdded::class);

    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'user', meta: ['source' => 'first']);
    $team->removeMember($user);

    $member = $team->addMember($user, 'admin', meta: ['source' => 'second']);

    expect(Member::withTrashed()->where('team_id', $team->getKey())->count())->toBe(1)
        ->and($member->trashed())->toBeFalse()
        ->and($member->role)->toBe('admin')
        ->and($member->meta->all())->toBe(['source' => 'second'])
        ->and($team->hasMember($user))->toBeTrue();

    Event::assertDispatchedTimes(TeamMemberAdded::class, 2);
});

it('revives an expired membership when the member is added again', function (): void {
    Event::fake(TeamMemberAdded::class);

    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'user', expiresAt: now()->subDay());

    $member = $team->addMember($user, 'admin');

    expect($member->isExpired())->toBeFalse()
        ->and($member->expires_at)->toBeNull()
        ->and($member->role)->toBe('admin')
        ->and($team->memberHasPermission($user, 'anything'))->toBeTrue()
        ->and($team->members()->count())->toBe(1);

    Event::assertDispatchedTimes(TeamMemberAdded::class, 2);
});

it('lets a fresh invite revive an expired membership', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'user', expiresAt: now()->subDay());
    $invite = Teams::for($team)->invites()->create(role: 'admin');

    $member = Teams::invites()->accept($invite, $user);

    expect($member->isExpired())->toBeFalse()
        ->and($member->role)->toBe('admin')
        ->and($member->accepted_invite_id)->toBe($invite->getKey())
        ->and($team->memberHasPermission($user, 'anything'))->toBeTrue();
});

it('counts a revival against the seat cap', function (): void {
    $team = Team::factory()->create();
    $expired = User::create();
    $team->addMember($expired, 'user', expiresAt: now()->subDay());
    $team->addMember(User::create(), 'user');
    Teams::for($team)->settings()->setMaxSeats(1);

    expect(fn () => $team->addMember($expired, 'user'))
        ->toThrow(TeamsException::class, 'seat limit');

    expect($team->findMember($expired)?->isExpired())->toBeTrue();
});
