<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\ContextActor;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    $this->world = TwoCompanies::make();
});

function contextActor(): ?ContextActor
{
    return app(TeamContext::class)->actor();
}

it('switches a member to their team and remembers who it was checked for', function (): void {
    Event::fake([TeamContextSet::class]);

    Teams::switchTo($this->world->a, $this->world->alice);

    expect(Teams::current()?->is($this->world->a))->toBeTrue()
        ->and(Project::pluck('team_id')->unique()->all())->toBe([$this->world->a->getKey()])
        ->and(contextActor()?->type)->toBe($this->world->alice->getMorphClass())
        ->and(contextActor()?->id)->toBe($this->world->alice->getKey());

    Event::assertDispatched(TeamContextSet::class, fn (TeamContextSet $event): bool => $event->team()?->is($this->world->a) === true && ! $event->multiple);
});

it('checks the authenticated user by default', function (): void {
    $this->actingAs($this->world->alice);

    Teams::switchTo($this->world->a);

    expect(Teams::current()?->is($this->world->a))->toBeTrue()
        ->and(contextActor()?->id)->toBe($this->world->alice->getKey());
});

it('works injected, without the facade', function (): void {
    app(TeamsManager::class)->switchTo($this->world->b, $this->world->bob);

    expect(Teams::current()?->is($this->world->b))->toBeTrue();
});

it('works under the fake', function (): void {
    Teams::fake();

    Teams::switchTo($this->world->a, $this->world->alice);

    expect(Teams::current()?->is($this->world->a))->toBeTrue();
});

it('refuses a team the user is not a member of and leaves the context alone', function (): void {
    Teams::switchTo($this->world->a, $this->world->alice);

    expect(fn () => Teams::switchTo($this->world->b, $this->world->alice))
        ->toThrow(TeamContextException::class, 'not an active member of team #'.$this->world->b->getKey());

    expect(Teams::current()?->is($this->world->a))->toBeTrue()
        ->and(contextActor()?->id)->toBe($this->world->alice->getKey());
});

it('refuses an expired membership', function (): void {
    Member::query()->where('team_id', $this->world->a->getKey())->update(['expires_at' => now()->subMinute()]);

    expect(fn () => Teams::switchTo($this->world->a, $this->world->alice))->toThrow(TeamContextException::class);

    expect(Teams::current())->toBeNull();
});

it('refuses without a user to check', function (): void {
    expect(fn () => Teams::switchTo($this->world->a))
        ->toThrow(TeamContextException::class, 'Switching teams needs a user');
});

it('refuses an unsaved team', function (): void {
    expect(fn () => Teams::switchTo(new Team, $this->world->alice))
        ->toThrow(TeamContextException::class, 'must be saved');
});

it('lets a member of a team above in when roles inherit down', function (): void {
    config()->set('teams.nesting.enabled', true);
    config()->set('teams.nesting.inherit_roles_down', true);

    $squad = Team::factory()->create();
    Teams::for($squad)->moveUnder($this->world->a);

    Teams::switchTo($squad, $this->world->alice);

    expect(Teams::current()?->is($squad))->toBeTrue();
});

it('remembers the user of a multi-team set too', function (): void {
    Teams::for($this->world->b)->members()->add($this->world->alice, 'user');

    Teams::setCurrentMany([$this->world->a, $this->world->b], $this->world->alice);

    expect(contextActor()?->id)->toBe($this->world->alice->getKey());
});

it('forgets the user when the unchecked setCurrent() takes over, and runAs() puts it back', function (): void {
    Teams::switchTo($this->world->a, $this->world->alice);

    $inside = Teams::runAs($this->world->b, fn (): ?ContextActor => contextActor());

    expect($inside)->toBeNull()
        ->and(contextActor()?->id)->toBe($this->world->alice->getKey());

    Teams::setCurrent($this->world->b);

    expect(contextActor())->toBeNull();

    Teams::switchTo($this->world->a, $this->world->alice);
    Teams::setCurrent(null);

    expect(contextActor())->toBeNull();
});

describe('ContextActor', function (): void {
    it('reads back only a well-formed stored actor', function (mixed $stored): void {
        expect(ContextActor::fromStored($stored))->toBeNull();
    })->with([
        'nothing' => [null],
        'a string' => ['user:1'],
        'no type' => [['id' => 1]],
        'an empty type' => [['type' => '', 'id' => 1]],
        'an empty id' => [['type' => 'user', 'id' => '']],
        'a float id' => [['type' => 'user', 'id' => 1.5]],
    ]);

    it('round-trips a string key', function (): void {
        $actor = ContextActor::fromStored((new ContextActor('user', 'abc'))->toStored());

        expect($actor?->type)->toBe('user')->and($actor?->id)->toBe('abc');
    });

    it('resolves the member, and nothing for a class or row that is gone', function (): void {
        expect(ContextActor::of($this->world->alice)->resolve()?->is($this->world->alice))->toBeTrue()
            ->and((new ContextActor('No\\Such\\Model', 1))->resolve())->toBeNull()
            ->and((new ContextActor(stdClass::class, 1))->resolve())->toBeNull()
            ->and((new ContextActor(User::class, 999_999))->resolve())->toBeNull();
    });
});
