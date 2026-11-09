<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::create();
});

describe('with start dates off (the default)', function (): void {
    it('refuses a start date rather than making the member active at once', function (): void {
        expect(fn () => Teams::for($this->team)->members()->add($this->user, 'user', startsAt: now()->addDay()))
            ->toThrow(TeamsException::class, 'teams.members.start_dates');

        expect($this->team->findMember($this->user))->toBeNull();
    });

    it('never reads the column', function (): void {
        $member = Member::factory()->for($this->team)->startingAt(now()->addDay())->create([
            'member_type' => $this->user->getMorphClass(),
            'member_id' => $this->user->getKey(),
        ]);

        expect($member->hasStarted())->toBeTrue()
            ->and($member->isActive())->toBeTrue()
            ->and($this->team->hasActiveMember($this->user))->toBeTrue()
            ->and($this->team->isAccessibleBy($this->user))->toBeTrue()
            ->and(Member::query()->upcoming()->count())->toBe(0);
    });

    it('never writes the column', function (): void {
        $member = Teams::for($this->team)->members()->add($this->user, 'user');

        expect($member->getAttributes())->not->toHaveKey('starts_at');
    });

    it('works on a host that has not published the starts_at migration', function (): void {
        Schema::table('team_members', static function (Blueprint $table): void {
            $table->dropColumn('starts_at');
        });

        Teams::for($this->team)->settings()->setMaxSeats(5);
        Teams::for($this->team)->members()->add($this->user, 'admin');
        Teams::for($this->team)->members()->add($this->user, 'admin', expiresAt: now()->addDay());

        expect($this->team->hasActiveMember($this->user))->toBeTrue()
            ->and($this->team->isAccessibleBy($this->user))->toBeTrue()
            ->and($this->team->memberHasPermission($this->user, 'anything'))->toBeTrue()
            ->and(Member::query()->upcoming()->count())->toBe(0);
    });
});

describe('with start dates on', function (): void {
    beforeEach(function (): void {
        config()->set('teams.members.start_dates', true);
    });

    it('books a membership that grants nothing until it starts', function (): void {
        $member = Teams::for($this->team)->members()->add($this->user, 'admin', startsAt: now()->addDay());

        expect($member->starts_at?->isFuture())->toBeTrue()
            ->and($member->hasStarted())->toBeFalse()
            ->and($member->isActive())->toBeFalse()
            ->and($member->role())->toBeNull()
            ->and($this->team->hasActiveMember($this->user))->toBeFalse()
            ->and($this->user->isActiveMemberOf($this->team))->toBeFalse()
            ->and($this->team->isAccessibleBy($this->user))->toBeFalse()
            ->and($this->team->memberHasRole($this->user, 'admin'))->toBeFalse()
            ->and($this->team->memberHasPermission($this->user, 'anything'))->toBeFalse()
            ->and($this->user->can('teams.anything', $this->team))->toBeFalse()
            ->and(Member::query()->active()->count())->toBe(0)
            ->and(Member::query()->upcoming()->pluck('id')->all())->toBe([$member->getKey()]);

        expect(fn () => Teams::switchTo($this->team, $this->user))->toThrow(TeamContextException::class)
            ->and(fn () => Teams::setCurrentMany([$this->team], $this->user))->toThrow(TeamContextException::class);
    });

    it('turns the membership on once the start passes', function (): void {
        Teams::for($this->team)->members()->add($this->user, 'admin', startsAt: now()->addDay());

        $this->travel(25)->hours();

        expect($this->team->hasActiveMember($this->user))->toBeTrue()
            ->and($this->team->isAccessibleBy($this->user))->toBeTrue()
            ->and($this->team->memberHasRole($this->user, 'admin'))->toBeTrue()
            ->and($this->team->memberHasPermission($this->user, 'anything'))->toBeTrue()
            ->and($this->team->findMember($this->user)?->role()?->key)->toBe('admin')
            ->and(Member::query()->upcoming()->count())->toBe(0);
    });

    it('keeps a not-yet-started member out of the context middleware', function (): void {
        Route::middleware('teams.context')->get('/teams/{team}/home', fn (): string => 'in');

        Teams::for($this->team)->members()->add($this->user, 'user', startsAt: now()->addHour());

        $this->actingAs($this->user)->get('/teams/'.$this->team->getKey().'/home')->assertNotFound();

        $this->travel(2)->hours();

        $this->actingAs($this->user)->get('/teams/'.$this->team->getKey().'/home')->assertOk();
    });

    it('treats a start date in the past or now as started', function (): void {
        $member = Teams::for($this->team)->members()->add($this->user, 'user', startsAt: now());

        expect($member->isActive())->toBeTrue()
            ->and($this->team->hasActiveMember($this->user))->toBeTrue();
    });

    it('refuses a membership that ends before it begins', function (): void {
        expect(fn () => Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->addDay(), startsAt: now()->addDays(2)))
            ->toThrow(TeamsException::class, 'must start before it expires');

        expect(fn () => Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->addDay(), startsAt: now()->addDay()))
            ->toThrow(TeamsException::class);

        Teams::for($this->team)->members()->add($this->user, 'user', expiresAt: now()->addDay());

        // A re-add keeps the existing expiry, so the window is checked against it.
        expect(fn () => Teams::for($this->team)->members()->add($this->user, 'user', startsAt: now()->addDays(2)))
            ->toThrow(TeamsException::class, 'must start before it expires');
    });

    it('moves the start on a re-add that names one, and keeps it on one that does not', function (): void {
        Teams::for($this->team)->members()->add($this->user, 'user', startsAt: now()->addDay());

        Teams::for($this->team)->members()->add($this->user, 'user');

        expect($this->team->findMember($this->user)?->starts_at?->isFuture())->toBeTrue();

        Teams::for($this->team)->members()->add($this->user, 'user', startsAt: now()->subMinute());

        expect($this->team->hasActiveMember($this->user))->toBeTrue();
    });

    it('revives an expired membership starting at once unless a start is given', function (): void {
        Member::factory()->for($this->team)->expired()->startingAt(now()->subWeek())->create([
            'member_type' => $this->user->getMorphClass(),
            'member_id' => $this->user->getKey(),
        ]);

        $revived = Teams::for($this->team)->members()->add($this->user, 'user');

        expect($revived->starts_at)->toBeNull()
            ->and($this->team->hasActiveMember($this->user))->toBeTrue();
    });

    it('lets a booked membership hold its seat', function (): void {
        Teams::for($this->team)->settings()->setMaxSeats(1);

        Teams::for($this->team)->members()->add($this->user, 'user', startsAt: now()->addWeek());

        expect(fn () => Teams::for($this->team)->members()->add(User::create(), 'user'))
            ->toThrow(TeamsException::class, 'seat limit');
    });

    it('takes a start date through the model, the action and the fake', function (): void {
        $fake = Teams::fake();

        $this->team->addMember($this->user, 'user', startsAt: now()->addDay());

        $other = User::create();
        app(AddMemberAction::class)->execute($this->team, new AddMemberData($other, 'user', startsAt: now()->addDays(2)));

        expect($this->team->findMember($this->user)?->hasStarted())->toBeFalse()
            ->and($this->team->findMember($other)?->hasStarted())->toBeFalse();

        $fake->assertMemberAdded($this->team, $this->user, 'user');
    });
});
