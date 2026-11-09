<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Log\Context\Repository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\ProbeJob;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;

/**
 * The race this pins: a member's context is captured into a job at dispatch, and the
 * membership is revoked before a worker picks the job up. With
 * `teams.context.recheck_queued_jobs` on the job re-checks against the database as it is
 * when it STARTS, so the revocation is caught and the job never runs.
 */
beforeEach(function (): void {
    $this->world = TwoCompanies::make();
    ProbeJob::$seen = [];

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.table', 'jobs');
    config()->set('queue.failed.driver', 'null');

    $this->failures = [];
    Queue::failing(function (JobFailed $event): void {
        $this->failures[] = $event->exception;
    });
});

/**
 * Work the queue as a separate worker would: no context, fresh scoped instances, and
 * several tries — a revoked context must fail once, not be retried.
 */
function workRecheckedJobs(): void
{
    Teams::setCurrent(null);
    app()->forgetScopedInstances();

    test()->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 3, '--sleep' => 0, '--memory' => 4096])->assertSuccessful();
}

function aliceMembershipOf(Team $team): Member
{
    /** @var Member */
    return Member::query()->where('team_id', $team->getKey())->firstOrFail();
}

describe('with the re-check off (the default)', function (): void {
    it('runs a job whose member was removed after dispatch, as before', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        ProbeJob::dispatch();

        Teams::for($this->world->a)->members()->remove($this->world->alice);

        workRecheckedJobs();

        expect(ProbeJob::$seen)->toHaveCount(1)
            ->and(ProbeJob::$seen[0]['current'])->toBe($this->world->a->getKey())
            ->and($this->failures)->toBe([]);
    });
});

describe('with the re-check on', function (): void {
    beforeEach(function (): void {
        config()->set('teams.context.recheck_queued_jobs', true);
    });

    it('runs the job while the member still belongs to the team', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        ProbeJob::dispatch();

        Event::fake([TeamContextSet::class]);
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toHaveCount(1)
            ->and(ProbeJob::$seen[0]['projects'])->toBe(['Apollo', 'Gemini'])
            ->and($this->failures)->toBe([]);

        Event::assertDispatched(TeamContextSet::class, fn (TeamContextSet $event): bool => $event->restored);
    });

    it('fails a job whose member was removed between dispatch and start — once, never run', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        ProbeJob::dispatch();

        Teams::for($this->world->a)->members()->remove($this->world->alice);

        Event::fake([TeamContextSet::class]);
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toBe([])
            ->and($this->failures)->toHaveCount(1)
            ->and($this->failures[0])->toBeInstanceOf(TeamContextException::class)
            ->and($this->failures[0]->getMessage())->toContain('may no longer act in team #'.$this->world->a->getKey())
            ->and(DB::table('jobs')->count())->toBe(0);

        // A revoked context is never announced as restored.
        Event::assertNotDispatched(TeamContextSet::class, fn (TeamContextSet $event): bool => $event->restored);
    });

    it('fails a job whose membership expired before it started', function (): void {
        aliceMembershipOf($this->world->a)->update(['expires_at' => now()->addHour()]);

        Teams::switchTo($this->world->a, $this->world->alice);
        ProbeJob::dispatch();

        $this->travel(2)->hours();
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toBe([])
            ->and($this->failures)->toHaveCount(1);
    });

    it('fails a job whose member no longer exists', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        ProbeJob::dispatch();

        $this->world->alice->delete();
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toBe([])
            ->and($this->failures[0]->getMessage())->toContain('no longer exists');
    });

    it('fails a job whose team was deleted', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        ProbeJob::dispatch();

        $this->world->a->delete();
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toBe([])
            ->and($this->failures)->toHaveCount(1);
    });

    it('fails a multi-team job when one of its teams was revoked', function (): void {
        Teams::for($this->world->b)->members()->add($this->world->alice, 'user');
        Teams::setCurrentMany([$this->world->a, $this->world->b], $this->world->alice);
        ProbeJob::dispatch();

        Teams::for($this->world->b)->members()->remove($this->world->alice);
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toBe([])
            ->and($this->failures[0]->getMessage())->toContain('team #'.$this->world->b->getKey());
    });

    it('does not re-check a context set by the unchecked setCurrent()', function (): void {
        Teams::setCurrent($this->world->a);
        ProbeJob::dispatch();

        Teams::for($this->world->a)->members()->remove($this->world->alice);
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toHaveCount(1)
            ->and($this->failures)->toBe([]);
    });

    it('catches a job dispatched by a request through the context middleware', function (): void {
        Route::middleware('teams.context')->post('/teams/{team}/report', function (): string {
            ProbeJob::dispatch();

            return 'queued';
        });

        $this->actingAs($this->world->alice)->post('/teams/'.$this->world->a->getKey().'/report')->assertOk();

        Teams::for($this->world->a)->members()->remove($this->world->alice);
        workRecheckedJobs();

        expect(ProbeJob::$seen)->toBe([])
            ->and($this->failures)->toHaveCount(1);
    });

    it('lets the other jobs on the queue run', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        ProbeJob::dispatch();
        Teams::switchTo($this->world->b, $this->world->bob);
        ProbeJob::dispatch();

        Teams::for($this->world->a)->members()->remove($this->world->alice);
        workRecheckedJobs();

        expect(array_column(ProbeJob::$seen, 'current'))->toBe([$this->world->b->getKey()])
            ->and($this->failures)->toHaveCount(1);
    });

    it('throws to the caller on the sync queue', function (): void {
        config()->set('queue.default', 'sync');

        Teams::switchTo($this->world->a, $this->world->alice);
        Member::query()->where('team_id', $this->world->a->getKey())->delete();

        expect(fn () => app(Dispatcher::class)->dispatch(new ProbeJob))->toThrow(TeamContextException::class);

        expect(ProbeJob::$seen)->toBe([]);
    });

    it('keeps a revocation to the hydration that found it', function (): void {
        Teams::switchTo($this->world->a, $this->world->alice);
        $payload = app(Repository::class)->dehydrate();

        Teams::for($this->world->a)->members()->remove($this->world->alice);

        app(Repository::class)->hydrate($payload);

        expect(Teams::current())->toBeNull();

        // A later hydration without a team context clears the stale revocation.
        app(Repository::class)->hydrate(null);

        expect(app(TeamContext::class)->pullRevocation())->toBeNull();
    });
});
