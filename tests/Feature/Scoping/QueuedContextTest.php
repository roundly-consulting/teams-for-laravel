<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\ProbeJob;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;

beforeEach(function (): void {
    $this->world = TwoCompanies::make();
    ProbeJob::$seen = [];

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.table', 'jobs');
    config()->set('queue.failed.driver', 'null');
});

/**
 * Forget everything this process knows — the context and every scoped instance — then
 * work the queue, as a separate worker process would.
 */
function workQueuedJobsInAFreshScope(): void
{
    Teams::setCurrent(null);
    app()->forgetScopedInstances();

    test()->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--memory' => 4096])->assertSuccessful();
}

it('restores the dispatching request\'s team inside the job', function (): void {
    Teams::setCurrent($this->world->a);
    ProbeJob::dispatch();

    Teams::setCurrent($this->world->b);
    ProbeJob::dispatch();

    Event::fake([TeamContextSet::class]);
    workQueuedJobsInAFreshScope();

    expect(ProbeJob::$seen)->toBe([
        ['current' => $this->world->a->getKey(), 'many' => [$this->world->a->getKey()], 'projects' => ['Apollo', 'Gemini']],
        ['current' => $this->world->b->getKey(), 'many' => [$this->world->b->getKey()], 'projects' => ['Apollo', 'Gemini']],
    ]);

    Event::assertDispatched(TeamContextSet::class, fn (TeamContextSet $event): bool => $event->restored && $event->team()?->is($this->world->a) === true);
});

it('restores a multi-team set', function (): void {
    Teams::for($this->world->b)->members()->add($this->world->alice, 'user');
    Teams::setCurrentMany([$this->world->a, $this->world->b], $this->world->alice);
    ProbeJob::dispatch();

    workQueuedJobsInAFreshScope();

    expect(ProbeJob::$seen[0]['current'])->toBeNull()
        ->and(ProbeJob::$seen[0]['many'])->toBe([$this->world->a->getKey(), $this->world->b->getKey()])
        ->and(ProbeJob::$seen[0]['projects'])->toBe(['Apollo', 'Apollo', 'Gemini', 'Gemini']);
});

it('runs a job dispatched without a context without one — and reads nothing', function (): void {
    ProbeJob::dispatch();

    // A previous job's context must not leak into the next one.
    Teams::setCurrent($this->world->a);
    ProbeJob::dispatch();
    Teams::setCurrent(null);
    ProbeJob::dispatch();

    workQueuedJobsInAFreshScope();

    expect(array_column(ProbeJob::$seen, 'current'))->toBe([null, $this->world->a->getKey(), null])
        ->and(array_column(ProbeJob::$seen, 'projects'))->toBe([[], ['Apollo', 'Gemini'], []]);
});

it('carries the runAs team into jobs dispatched inside it', function (): void {
    Teams::runAs($this->world->b, fn () => ProbeJob::dispatch());

    workQueuedJobsInAFreshScope();

    expect(ProbeJob::$seen[0]['current'])->toBe($this->world->b->getKey());
});

it('does not carry the withoutTeamScope bypass into a job', function (): void {
    Teams::withoutTeamScope(fn () => ProbeJob::dispatch());

    workQueuedJobsInAFreshScope();

    expect(ProbeJob::$seen[0]['projects'])->toBe([]);
});

it('starts a console command with no team', function (): void {
    Teams::setCurrent(null);
    app()->forgetScopedInstances();

    expect(Teams::current())->toBeNull();
});
