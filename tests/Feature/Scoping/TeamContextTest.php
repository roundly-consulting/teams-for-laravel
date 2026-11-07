<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    $this->world = TwoCompanies::make();
});

it('starts without a context', function (): void {
    expect(Teams::current())->toBeNull()
        ->and(Teams::currentMany())->toBeEmpty();
});

it('sets, reads and clears the current team', function (): void {
    Event::fake([TeamContextSet::class]);

    Teams::setCurrent($this->world->a);

    expect(Teams::current()?->is($this->world->a))->toBeTrue()
        ->and(Teams::currentMany()->modelKeys())->toBe([$this->world->a->getKey()]);

    Teams::setCurrent(null);

    expect(Teams::current())->toBeNull()
        ->and(Project::count())->toBe(0);

    Event::assertDispatched(TeamContextSet::class, fn (TeamContextSet $event): bool => $event->team()?->is($this->world->a) === true && ! $event->multiple);
    Event::assertDispatched(TeamContextSet::class, fn (TeamContextSet $event): bool => $event->teams->isEmpty() && $event->team() === null);
});

it('works injected, without the facade', function (): void {
    $teams = app(TeamsManager::class);

    $teams->setCurrent($this->world->b);

    expect($teams->current()?->is($this->world->b))->toBeTrue()
        ->and(Project::pluck('team_id')->unique()->all())->toBe([$this->world->b->getKey()]);
});

it('refuses an unsaved team', function (): void {
    expect(fn () => Teams::setCurrent(new Team))->toThrow(TeamContextException::class);
});

describe('runAs', function (): void {
    it('runs as the team and restores the previous team', function (): void {
        Teams::setCurrent($this->world->a);

        $seen = Teams::runAs($this->world->b, fn (Team $team): array => [$team->getKey(), Teams::current()?->getKey(), Project::pluck('team_id')->unique()->all()]);

        expect($seen)->toBe([$this->world->b->getKey(), $this->world->b->getKey(), [$this->world->b->getKey()]])
            ->and(Teams::current()?->is($this->world->a))->toBeTrue();
    });

    it('restores the context after an exception', function (): void {
        Teams::setCurrent($this->world->a);

        expect(fn () => Teams::runAs($this->world->b, fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class);

        expect(Teams::current()?->is($this->world->a))->toBeTrue()
            ->and(Project::pluck('team_id')->unique()->all())->toBe([$this->world->a->getKey()]);
    });

    it('restores "no context" and a multi-team set exactly', function (): void {
        Teams::runAs($this->world->a, fn () => null);

        expect(Teams::currentMany())->toBeEmpty()
            ->and(Project::count())->toBe(0);

        Teams::for($this->world->b)->members()->add($this->world->alice, 'user');
        Teams::setCurrentMany([$this->world->a, $this->world->b], $this->world->alice);

        Teams::runAs($this->world->a, fn () => expect(Project::count())->toBe(2));

        expect(Teams::current())->toBeNull()
            ->and(Teams::currentMany()->modelKeys())->toBe([$this->world->a->getKey(), $this->world->b->getKey()])
            ->and(Project::count())->toBe(4);
    });

    it('nests', function (): void {
        Teams::runAs($this->world->a, function (): void {
            Teams::runAs($this->world->b, fn () => expect(Teams::current()?->is($this->world->b))->toBeTrue());

            expect(Teams::current()?->is($this->world->a))->toBeTrue();
        });

        expect(Teams::current())->toBeNull();
    });

    it('is reachable from the team handle', function (): void {
        expect(Teams::for($this->world->b)->run(fn (): int => Project::count()))->toBe(2)
            ->and(Teams::current())->toBeNull();
    });
});

describe('multi-team mode', function (): void {
    it('accepts only teams the authenticated user is an active member of', function (): void {
        Event::fake([TeamContextSet::class]);
        $c = Team::factory()->create();
        Teams::for($c)->members()->add($this->world->alice, 'user');
        $this->actingAs($this->world->alice);

        Teams::setCurrentMany(collect([$this->world->a, $c, $this->world->a]));

        expect(Teams::current())->toBeNull()
            ->and(Teams::currentMany()->modelKeys())->toBe([$this->world->a->getKey(), $c->getKey()]);

        Event::assertDispatched(TeamContextSet::class, fn (TeamContextSet $event): bool => $event->multiple && $event->teams->count() === 2 && $event->team() === null);

        expect(fn () => Teams::setCurrentMany([$this->world->a, $this->world->b]))
            ->toThrow(TeamContextException::class, 'not an active member of team #'.$this->world->b->getKey());

        // All or nothing: the refused call left the previous set in place.
        expect(Teams::currentMany()->modelKeys())->toBe([$this->world->a->getKey(), $c->getKey()]);
    });

    it('refuses an expired membership', function (): void {
        $c = Team::factory()->create();
        Teams::for($c)->members()->add($this->world->alice, 'user', expiresAt: now()->subMinute());

        expect(fn () => Teams::setCurrentMany([$this->world->a, $c], $this->world->alice))->toThrow(TeamContextException::class);
    });

    it('checks memberships of an explicitly given user', function (): void {
        Teams::setCurrentMany([$this->world->b], $this->world->bob);

        expect(Project::count())->toBe(2);

        expect(fn () => Teams::setCurrentMany([$this->world->b], $this->world->alice))->toThrow(TeamContextException::class);
    });

    it('needs a user', function (): void {
        expect(fn () => Teams::setCurrentMany([$this->world->a]))->toThrow(TeamContextException::class, 'needs a user');
    });

    it('scopes an empty set to nothing', function (): void {
        Teams::setCurrentMany([], User::create());

        expect(Project::count())->toBe(0)
            ->and(fn () => Project::create(['name' => 'x']))->toThrow(TeamContextException::class);
    });
});

it('acts in a team from a test', function (): void {
    $this->actingInTeam($this->world->alice, $this->world->a);

    expect(auth()->user()?->is($this->world->alice))->toBeTrue()
        ->and(Teams::current()?->is($this->world->a))->toBeTrue()
        ->and(Project::count())->toBe(2);
});
