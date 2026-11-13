<?php

declare(strict_types=1);

use Illuminate\Log\Context\Repository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Queue as BaseQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Scoping\ContextActor;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\EncryptedTeamArgumentJob;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\ProbeJob;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\SyncSeatsListener;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\TeamArgumentJob;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\TeamLeft;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;

/**
 * The gap this closes: a platform job that takes its team as an argument (seat sync,
 * metering) is dispatched exactly when a member leaves, is offboarded or a team is
 * deleted — from that member's context. With `teams.context.recheck_queued_jobs` on it
 * inherited the context and always failed. Marked `WithoutTeamContext`, or dispatched
 * inside `Teams::withoutContext()`, it carries no context and runs.
 */
beforeEach(function (): void {
    $this->world = TwoCompanies::make();
    ProbeJob::$seen = [];
    TeamArgumentJob::$seen = [];

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.table', 'jobs');
    config()->set('queue.failed.driver', 'null');
    config()->set('teams.context.recheck_queued_jobs', true);

    $this->failures = [];
    Queue::failing(function (JobFailed $event): void {
        $this->failures[] = $event->exception;
    });
});

/**
 * Work the queue as a separate worker process would: no context, fresh scoped instances.
 */
function workPlatformJobs(): void
{
    Teams::setCurrent(null);
    app()->forgetScopedInstances();

    test()->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 3, '--sleep' => 0, '--memory' => 4096])->assertSuccessful();
}

/**
 * The hidden context keys of every queued payload.
 *
 * @return list<list<string>>
 */
function queuedHiddenContextKeys(): array
{
    return DB::table('jobs')->orderBy('id')->pluck('payload')
        ->map(static function (mixed $payload): array {
            $context = json_decode((string) $payload, true)['illuminate:log:context'] ?? null;

            return array_keys($context['hidden'] ?? []);
        })
        ->values()
        ->all();
}

/**
 * Build a payload the way every queue driver does — through `createPayload()` and its
 * hooks — without a database or redis queue behind it.
 *
 * @return array<string, mixed>
 */
function payloadOf(object $job, string $connection = 'database'): array
{
    $queue = new class extends BaseQueue
    {
        public function build(object $job): string
        {
            return $this->createPayload($job, 'default');
        }
    };

    $queue->setContainer(app());
    $queue->setConnectionName($connection);

    return json_decode($queue->build($job), true);
}

/**
 * Forget this process's context and hydrate a payload's, exactly as the worker does on
 * `JobProcessing`.
 *
 * @param  array<string, mixed>  $payload
 */
function hydrateLikeTheWorker(array $payload): void
{
    Teams::setCurrent(null);
    app()->forgetScopedInstances();

    app(Repository::class)->hydrate($payload['illuminate:log:context'] ?? null);
}

describe('the WithoutTeamContext marker', function (): void {
    it('runs a marked job dispatched by a member who has since left — the re-check never sees it', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        TeamArgumentJob::dispatch($this->world->a->getKey());
        ProbeJob::dispatch();

        Teams::for($this->world->a)->members()->remove($this->world->alice);
        workPlatformJobs();

        expect(TeamArgumentJob::$seen)->toBe([[
            'current' => null,
            'many' => [],
            'ambient' => [],
            'own' => ['Apollo', 'Gemini'],
        ]]);

        // The unmarked job dispatched from the same context still fails, exactly as before.
        expect(ProbeJob::$seen)->toBe([])
            ->and($this->failures)->toHaveCount(1)
            ->and($this->failures[0])->toBeInstanceOf(TeamContextException::class);
    });

    it('queues no team keys, and keeps the rest of the context', function (): void {
        Context::add('trace', 'abc');
        Context::addHidden('tenant-region', 'eu');
        Teams::switchTo($this->world->a, $this->world->alice);

        TeamArgumentJob::dispatch($this->world->a->getKey());
        ProbeJob::dispatch();

        expect(queuedHiddenContextKeys())->toBe([
            ['tenant-region'],
            ['tenant-region', TeamContext::KEYS, TeamContext::MULTIPLE, TeamContext::ACTOR],
        ]);

        $payload = json_decode((string) DB::table('jobs')->orderBy('id')->value('payload'), true);

        expect($payload['illuminate:log:context']['data'])->toHaveKey('trace');
    });

    it('runs with no team even when the worker just ran another team\'s job', function (): void {
        Teams::setCurrent($this->world->a);
        ProbeJob::dispatch();
        Teams::setCurrent($this->world->b);
        TeamArgumentJob::dispatch($this->world->b->getKey());

        workPlatformJobs();

        expect(ProbeJob::$seen[0]['current'])->toBe($this->world->a->getKey())
            ->and(TeamArgumentJob::$seen[0]['current'])->toBeNull()
            ->and(TeamArgumentJob::$seen[0]['ambient'])->toBe([])
            ->and(TeamArgumentJob::$seen[0]['own'])->toBe(['Apollo', 'Gemini']);
    });

    it('reads the marker of an encrypted job before it is encrypted', function (): void {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        Teams::switchTo($this->world->a, $this->world->alice);
        EncryptedTeamArgumentJob::dispatch($this->world->a->getKey());

        expect(queuedHiddenContextKeys())->toBe([[]]);

        Teams::for($this->world->a)->members()->remove($this->world->alice);
        workPlatformJobs();

        expect(TeamArgumentJob::$seen[0]['current'])->toBeNull()
            ->and($this->failures)->toBe([]);
    });

    it('honours the marker on a queued listener', function (): void {
        Event::listen(TeamLeft::class, SyncSeatsListener::class);

        Teams::switchTo($this->world->a, $this->world->alice);
        Teams::for($this->world->a)->members()->remove($this->world->alice);
        event(new TeamLeft($this->world->a->getKey()));

        workPlatformJobs();

        expect(TeamArgumentJob::$seen)->toHaveCount(1)
            ->and(TeamArgumentJob::$seen[0]['current'])->toBeNull()
            ->and($this->failures)->toBe([]);
    });

    it('starts a chain from a team context with a marked job: neither job carries the team', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        Bus::chain([new TeamArgumentJob($this->world->a->getKey()), new ProbeJob])->dispatch();

        Teams::for($this->world->a)->members()->remove($this->world->alice);
        workPlatformJobs();

        // The chain continues from the marked job's empty context, so the unmarked job
        // after it runs without a team — and is not failed by the re-check.
        expect(TeamArgumentJob::$seen[0]['current'])->toBeNull()
            ->and(ProbeJob::$seen)->toBe([['current' => null, 'many' => [], 'projects' => []]])
            ->and($this->failures)->toBe([]);
    });

    it('strips the team from a marked job chained after an unmarked one', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        Bus::chain([new ProbeJob, new TeamArgumentJob($this->world->a->getKey())])->dispatch();

        workPlatformJobs();

        expect(ProbeJob::$seen[0]['current'])->toBe($this->world->a->getKey())
            ->and(TeamArgumentJob::$seen[0]['current'])->toBeNull()
            ->and(TeamArgumentJob::$seen[0]['own'])->toBe(['Apollo', 'Gemini'])
            ->and($this->failures)->toBe([]);
    });
});

describe('the payload round trip', function (): void {
    it('restores no team from a marked job\'s payload, and the team from an unmarked one', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        Context::add('trace', 'abc');

        $marked = payloadOf(new TeamArgumentJob($this->world->a->getKey()));
        $unmarked = payloadOf(new ProbeJob);

        hydrateLikeTheWorker($marked);

        expect(Teams::current())->toBeNull()
            ->and(app(TeamContext::class)->actor())->toBeNull()
            ->and(Context::get('trace'))->toBe('abc');

        hydrateLikeTheWorker($unmarked);

        expect(Teams::current()?->getKey())->toBe($this->world->a->getKey())
            ->and(app(TeamContext::class)->actor())->toBeInstanceOf(ContextActor::class);
    });

    it('queues no context at all when the team was all there was', function (): void {
        Teams::setCurrent($this->world->a);

        $payload = payloadOf(new TeamArgumentJob($this->world->a->getKey()));

        expect($payload)->toHaveKey('illuminate:log:context')
            ->and($payload['illuminate:log:context'])->toBeNull();

        hydrateLikeTheWorker($payload);

        expect(Teams::current())->toBeNull();
    });

    it('leaves the payload of a marked job dispatched without a context alone', function (): void {
        Context::add('trace', 'abc');

        $payload = payloadOf(new TeamArgumentJob($this->world->a->getKey()));

        expect($payload['illuminate:log:context']['data'])->toHaveKey('trace')
            ->and($payload['illuminate:log:context']['hidden'])->toBe([]);
    });

    it('builds the payload for a connection it cannot look up', function (): void {
        Teams::setCurrent($this->world->a);

        $payload = payloadOf(new TeamArgumentJob($this->world->a->getKey()), 'not-configured');

        expect($payload['illuminate:log:context'])->toBeNull();
    });
});

describe('the sync queue', function (): void {
    beforeEach(function (): void {
        config()->set('queue.default', 'sync');
    });

    it('runs a marked job without the team and gives the dispatcher its context back', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        $before = app(TeamContext::class)->snapshot();

        // The member is gone: an unmarked job would throw TeamContextException here.
        Teams::for($this->world->a)->members()->remove($this->world->alice);

        TeamArgumentJob::dispatch($this->world->a->getKey());

        expect(TeamArgumentJob::$seen[0]['current'])->toBeNull()
            ->and(TeamArgumentJob::$seen[0]['own'])->toBe(['Apollo', 'Gemini'])
            ->and(app(TeamContext::class)->snapshot())->toEqual($before)
            ->and(Teams::current()?->getKey())->toBe($this->world->a->getKey());
    });

    it('still throws for an unmarked job whose member is gone', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        Teams::for($this->world->a)->members()->remove($this->world->alice);

        expect(fn () => dispatch_sync(new ProbeJob))->toThrow(TeamContextException::class);
    });

    it('gives the context back when the marked job throws', function (): void {
        Teams::setCurrentMany([$this->world->a], $this->world->alice);
        $before = app(TeamContext::class)->snapshot();

        expect(fn () => TeamArgumentJob::dispatch($this->world->a->getKey(), fail: true))
            ->toThrow(RuntimeException::class, 'The platform job failed.');

        expect(TeamArgumentJob::$seen[0]['many'])->toBe([])
            ->and(app(TeamContext::class)->snapshot())->toEqual($before)
            ->and(Teams::currentMany()->modelKeys())->toBe([$this->world->a->getKey()]);
    });

    it('gives each level its context back when marked jobs nest', function (): void {
        Teams::setCurrent($this->world->b);

        TeamArgumentJob::dispatch($this->world->a->getKey(), then: new TeamArgumentJob($this->world->b->getKey()));

        expect(array_column(TeamArgumentJob::$seen, 'current'))->toBe([null, null])
            ->and(Teams::current()?->getKey())->toBe($this->world->b->getKey());
    });

    it('leaves the dispatcher without a team when it had none', function (): void {
        TeamArgumentJob::dispatch($this->world->a->getKey());

        expect(TeamArgumentJob::$seen)->toHaveCount(1)
            ->and(Teams::current())->toBeNull();
    });
});

describe('Teams::withoutContext()', function (): void {
    it('dispatches a job with no team context — it runs after the member left', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        $before = app(TeamContext::class)->snapshot();

        $result = Teams::withoutContext(function (): string {
            ProbeJob::dispatch();

            return 'queued';
        });

        expect($result)->toBe('queued')
            ->and(app(TeamContext::class)->snapshot())->toEqual($before)
            ->and(queuedHiddenContextKeys())->toBe([[]]);

        Teams::for($this->world->a)->members()->remove($this->world->alice);
        workPlatformJobs();

        expect(ProbeJob::$seen)->toBe([['current' => null, 'many' => [], 'projects' => []]])
            ->and($this->failures)->toBe([]);
    });

    it('dispatches a returned PendingDispatch inside the suspended context', function (): void {
        Teams::setCurrent($this->world->a);

        $result = Teams::withoutContext(fn () => ProbeJob::dispatch());

        expect($result)->toBeNull()
            ->and(queuedHiddenContextKeys())->toBe([[]])
            ->and(Teams::current()?->getKey())->toBe($this->world->a->getKey());
    });

    it('suspends the context inside the callback, for reads too', function (): void {
        Teams::setCurrent($this->world->a);

        $seen = Teams::withoutContext(fn (): array => [
            'current' => Teams::current(),
            'has' => app(TeamContext::class)->has(),
        ]);

        expect($seen)->toBe(['current' => null, 'has' => false]);
    });

    it('restores the context when the callback throws', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        $before = app(TeamContext::class)->snapshot();

        expect(fn () => Teams::withoutContext(function (): never {
            throw new RuntimeException('dispatch failed');
        }))->toThrow(RuntimeException::class, 'dispatch failed');

        expect(app(TeamContext::class)->snapshot())->toEqual($before);
    });

    it('nests: each level restores what it found', function (): void {
        Teams::setCurrentMany([$this->world->a], $this->world->alice);
        $outer = app(TeamContext::class)->snapshot();

        Teams::withoutContext(function (): void {
            Teams::runAs($this->world->b, function (): void {
                $inner = app(TeamContext::class)->snapshot();

                Teams::withoutContext(fn () => expect(Teams::current())->toBeNull());

                expect(app(TeamContext::class)->snapshot())->toEqual($inner)
                    ->and(Teams::current()?->getKey())->toBe($this->world->b->getKey());
            });

            expect(app(TeamContext::class)->has())->toBeFalse();
        });

        expect(app(TeamContext::class)->snapshot())->toEqual($outer);
    });

    it('announces the suspension and the restore, and nothing without a context', function (): void {
        Event::fake([TeamContextSet::class]);

        Teams::withoutContext(fn (): null => null);
        Event::assertNotDispatched(TeamContextSet::class);

        Teams::setCurrent($this->world->a);
        Teams::withoutContext(fn (): null => null);

        $events = Event::dispatched(TeamContextSet::class)->map(fn (array $args): mixed => $args[0]->team()?->getKey())->all();

        expect($events)->toBe([$this->world->a->getKey(), null, $this->world->a->getKey()]);
    });

    it('is the same call through the injected manager', function (): void {
        Teams::setCurrent($this->world->a);

        $current = app(TeamsManager::class)->withoutContext(fn (): mixed => Teams::current());

        expect($current)->toBeNull()
            ->and(Teams::current()?->getKey())->toBe($this->world->a->getKey());
    });

    it('keeps working under the fake and records nothing', function (): void {
        $fake = Teams::fake();
        Teams::setCurrent($this->world->a);

        $current = Teams::withoutContext(fn (): mixed => Teams::current());

        expect($current)->toBeNull()
            ->and(Teams::current()?->getKey())->toBe($this->world->a->getKey());

        $fake->assertNothingRecorded();
    });
});
