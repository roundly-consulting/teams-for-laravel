<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Task;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\ProbeJob;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;

/**
 * The acceptance scenario: two companies with identical data behind a small app. Every
 * endpoint and job of company A sees only A's rows; company B's routes 404 for a member of
 * only A; and without the context nothing is returned at all.
 */
beforeEach(function (): void {
    $this->world = TwoCompanies::make();
    ProbeJob::$seen = [];
    config()->set('queue.default', 'database');
    config()->set('queue.failed.driver', 'null');

    Route::middleware([SubstituteBindings::class, 'teams.context'])->prefix('/teams/{team}')->group(function (): void {
        Route::get('/projects', fn () => Project::with('tasks')->orderBy('name')->get()->map(fn (Project $p): array => [
            'name' => $p->name,
            'team' => $p->team_id,
            'tasks' => $p->tasks->pluck('title')->all(),
        ]));
        Route::get('/projects/{project}', fn (Team $team, Project $project) => $project->only('name', 'team_id'));
        Route::post('/projects', fn (Request $request) => Project::create(['name' => $request->string('name')->toString()])->only('name', 'team_id'));
        Route::patch('/projects/{project}', function (Team $team, Project $project, Request $request) {
            $project->update(['name' => $request->string('name')->toString()]);

            return $project->only('name');
        });
        Route::delete('/projects/{project}', fn (Team $team, Project $project) => ['deleted' => $project->delete()]);
        Route::get('/stats', fn () => ['projects' => Project::count(), 'tasks' => Task::count()]);
        Route::post('/reports', function () {
            ProbeJob::dispatch();

            return response()->noContent();
        });
    });

    Route::get('/forgot-the-middleware', fn () => ['projects' => Project::count(), 'tasks' => Task::count()]);
});

it('shows company A only its own rows on every endpoint', function (): void {
    $a = $this->world->a->getKey();
    $mine = $this->world->projectOf($this->world->a);
    $as = $this->actingAs($this->world->alice);

    $as->getJson("/teams/{$a}/projects")->assertOk()->assertExactJson([
        ['name' => 'Apollo', 'team' => $a, 'tasks' => ['Apollo kickoff']],
        ['name' => 'Gemini', 'team' => $a, 'tasks' => ['Gemini kickoff']],
    ]);
    $as->getJson("/teams/{$a}/stats")->assertExactJson(['projects' => 2, 'tasks' => 2]);
    $as->getJson("/teams/{$a}/projects/{$mine->getKey()}")->assertExactJson(['name' => 'Apollo', 'team_id' => $a]);
    $as->postJson("/teams/{$a}/projects", ['name' => 'Mercury'])->assertOk()->assertExactJson(['name' => 'Mercury', 'team_id' => $a]);
    $as->patchJson("/teams/{$a}/projects/{$mine->getKey()}", ['name' => 'Apollo II'])->assertOk();
    $as->deleteJson("/teams/{$a}/projects/{$mine->getKey()}")->assertExactJson(['deleted' => true]);

    // Company B is exactly as it was.
    expect(Project::forTeam($this->world->b)->orderBy('name')->pluck('name')->all())->toBe(['Apollo', 'Gemini']);
});

it('never lets company A reach a company B row through A\'s own routes', function (): void {
    $a = $this->world->a->getKey();
    $theirs = $this->world->projectOf($this->world->b)->getKey();
    $as = $this->actingAs($this->world->alice);

    $as->getJson("/teams/{$a}/projects/{$theirs}")->assertNotFound();
    $as->patchJson("/teams/{$a}/projects/{$theirs}", ['name' => 'Hijacked'])->assertNotFound();
    $as->deleteJson("/teams/{$a}/projects/{$theirs}")->assertNotFound();

    expect(Project::forTeam($this->world->b)->orderBy('name')->pluck('name')->all())->toBe(['Apollo', 'Gemini']);
});

it('answers 404 on every company B route for a member of only company A', function (string $method, string $path): void {
    $b = $this->world->b->getKey();
    $theirs = $this->world->projectOf($this->world->b)->getKey();

    $this->actingAs($this->world->alice)
        ->json($method, str_replace(['{b}', '{p}'], [(string) $b, (string) $theirs], $path), ['name' => 'x'])
        ->assertNotFound();

    expect(Project::forTeam($this->world->b)->count())->toBe(2)
        ->and(ProbeJob::$seen)->toBe([]);
})->with([
    ['GET', '/teams/{b}/projects'],
    ['GET', '/teams/{b}/projects/{p}'],
    ['POST', '/teams/{b}/projects'],
    ['PATCH', '/teams/{b}/projects/{p}'],
    ['DELETE', '/teams/{b}/projects/{p}'],
    ['GET', '/teams/{b}/stats'],
    ['POST', '/teams/{b}/reports'],
]);

it('runs company A\'s jobs as company A', function (): void {
    $this->actingAs($this->world->alice)->postJson('/teams/'.$this->world->a->getKey().'/reports')->assertNoContent();
    $this->actingAs($this->world->bob)->postJson('/teams/'.$this->world->b->getKey().'/reports')->assertNoContent();

    Teams::setCurrent(null);
    app()->forgetScopedInstances();
    $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--memory' => 4096])->assertSuccessful();

    expect(array_column(ProbeJob::$seen, 'current'))->toBe([$this->world->a->getKey(), $this->world->b->getKey()])
        ->and(array_column(ProbeJob::$seen, 'projects'))->toBe([['Apollo', 'Gemini'], ['Apollo', 'Gemini']]);
});

it('returns nothing — not everything — once the context is gone', function (): void {
    $this->actingAs($this->world->alice)->getJson('/forgot-the-middleware')->assertExactJson(['projects' => 0, 'tasks' => 0]);
});
