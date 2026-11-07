<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\UuidTeam;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    $this->world = TwoCompanies::make();

    // SubstituteBindings deliberately listed FIRST: the package's priority entry must still
    // run the context middleware before it, or {project} would be bound with no context.
    Route::middleware([SubstituteBindings::class, 'teams.context'])->group(function (): void {
        Route::get('/teams/{team}/projects', fn (Team $team) => [
            'team' => $team->getKey(),
            'projects' => Project::orderBy('name')->pluck('name'),
        ]);
        Route::get('/teams/{team}/projects/{project}', fn (Team $team, Project $project) => ['id' => $project->getKey()]);
        Route::get('/by-name/{team:name}/projects', fn () => Project::pluck('team_id'));
        Route::get('/no-team', fn () => 'nothing to read');
    });

    Route::middleware('teams.context:company')->get('/companies/{company}/projects', fn () => Project::pluck('team_id'));
    Route::get('/unscoped/projects', fn () => Project::pluck('name'));
});

it('lets a member in and scopes the request to their team', function (): void {
    $this->actingAs($this->world->alice)
        ->getJson('/teams/'.$this->world->a->getKey().'/projects')
        ->assertOk()
        ->assertExactJson(['team' => $this->world->a->getKey(), 'projects' => ['Apollo', 'Gemini']]);

    expect(Teams::current()?->is($this->world->a))->toBeTrue();
});

it('answers 404 to a non-member, exactly like a team that does not exist', function (): void {
    $this->actingAs($this->world->alice)->getJson('/teams/'.$this->world->b->getKey().'/projects')->assertNotFound();
    $this->actingAs($this->world->alice)->getJson('/teams/999999/projects')->assertNotFound();
    // Text a bigint key column rejects outright on Postgres — never a 500.
    $this->actingAs($this->world->alice)->getJson('/teams/not-a-key/projects')->assertNotFound();
    $this->actingAs($this->world->alice)->getJson('/teams/99999999999999999999999/projects')->assertNotFound();
    $this->actingAs($this->world->alice)->getJson('/teams/-1/projects')->assertNotFound();

    expect(Teams::current())->toBeNull();
});

it('answers 404 to a malformed id of a UUID-keyed team model', function (): void {
    config()->set('teams.models.team', UuidTeam::class);

    $this->actingAs($this->world->alice)->getJson('/teams/not-a-uuid/projects')->assertNotFound();
});

it('answers 404 to an expired membership', function (): void {
    Teams::for($this->world->a)->members()->add($this->world->bob, 'user', expiresAt: now()->subSecond());

    $this->actingAs($this->world->bob)->getJson('/teams/'.$this->world->a->getKey().'/projects')->assertNotFound();
});

it('answers 404 to a guest and for a soft-deleted team', function (): void {
    $this->getJson('/teams/'.$this->world->a->getKey().'/projects')->assertNotFound();

    $this->world->a->delete();

    $this->actingAs($this->world->alice)->getJson('/teams/'.$this->world->a->getKey().'/projects')->assertNotFound();
});

it('answers 403 instead when configured to', function (): void {
    config()->set('teams.context.deny_status', 403);

    $this->actingAs($this->world->alice)->getJson('/teams/'.$this->world->b->getKey().'/projects')->assertForbidden();
});

it('binds scoped route models inside the context', function (): void {
    $mine = $this->world->projectOf($this->world->a);
    $theirs = $this->world->projectOf($this->world->b);

    $this->actingAs($this->world->alice)
        ->getJson('/teams/'.$this->world->a->getKey().'/projects/'.$mine->getKey())
        ->assertOk()
        ->assertExactJson(['id' => $mine->getKey()]);

    // Team B's project under team A's URL: the scoped binding finds nothing.
    $this->actingAs($this->world->alice)
        ->getJson('/teams/'.$this->world->a->getKey().'/projects/'.$theirs->getKey())
        ->assertNotFound();
});

it('binds the team by the route\'s binding field', function (): void {
    $this->actingAs($this->world->alice)->getJson('/by-name/Acme/projects')->assertOk()
        ->assertExactJson([$this->world->a->getKey(), $this->world->a->getKey()]);

    $this->actingAs($this->world->alice)->getJson('/by-name/Globex/projects')->assertNotFound();
});

it('reads another parameter when told to, or from config', function (): void {
    $this->actingAs($this->world->bob)->getJson('/companies/'.$this->world->b->getKey().'/projects')->assertOk()
        ->assertExactJson([$this->world->b->getKey(), $this->world->b->getKey()]);

    config()->set('teams.context.route_parameter', 'company');
    Route::middleware('teams.context')->get('/configured/{company}', fn () => Project::count());

    $this->actingAs($this->world->bob)->getJson('/configured/'.$this->world->b->getKey())->assertOk()->assertContent('2');
});

it('refuses a route without the team parameter', function (): void {
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->world->alice)->get('/no-team'))
        ->toThrow(TeamContextException::class, 'no [team] parameter');
});

it('returns nothing, not everything, from a route without the middleware', function (): void {
    $this->actingAs($this->world->alice)->getJson('/unscoped/projects')->assertOk()->assertExactJson([]);
});

it('lets a team member through via a role inherited from a parent team', function (): void {
    config()->set('teams.nesting.enabled', true);
    config()->set('teams.nesting.inherit_roles_down', true);
    $department = Team::factory()->create();
    Teams::for($department)->moveUnder($this->world->a);

    $this->actingAs($this->world->alice)->getJson('/teams/'.$department->getKey().'/projects')->assertOk();

    config()->set('teams.nesting.inherit_roles_down', false);

    $this->actingAs($this->world->alice)->getJson('/teams/'.$department->getKey().'/projects')->assertNotFound();
});

it('rejects an invalid deny status', function (): void {
    config()->set('teams.context.deny_status', 500);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs(User::create())->get('/teams/'.$this->world->a->getKey().'/projects'))
        ->toThrow(InvalidConfigurationException::class);
});
