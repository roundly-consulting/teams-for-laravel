<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Broken\ColumnlessNote;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Broken\UnindexedNote;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Document;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\ScopedTicket;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Task;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\VendorTicket;

$fixtures = __DIR__.'/../../Fixtures/Scoping';

it('lists every scoped model under the discovery paths and passes when all are healthy', function () use ($fixtures): void {
    config()->set('teams.scoping.discover', [$fixtures.'/Healthy', $fixtures.'/does-not-exist']);

    $reports = Teams::scopedModels();

    expect(array_map(fn ($report): string => $report->model, $reports))->toBe([
        Document::class,
        Project::class,
        ScopedTicket::class,
        Task::class,
    ])
        ->and(collect($reports)->every->isHealthy())->toBeTrue()
        ->and($reports[0]->column)->toBe('company_id')
        ->and($reports[2]->table)->toBe('vendor_tickets');

    $this->artisan('teams:doctor')
        ->expectsOutputToContain('Every team-scoped model has its team column and index.')
        ->assertSuccessful();
});

it('fails on a scoped table without the column or without an index', function () use ($fixtures): void {
    config()->set('teams.scoping.discover', [$fixtures]);

    $reports = collect(Teams::scopedModels())->keyBy('model');

    expect($reports)->toHaveCount(6)
        ->and($reports[UnindexedNote::class]->problems())->toBe(['no index starts with [team_id]'])
        ->and($reports[ColumnlessNote::class]->problems())->toBe(['column [team_id] is missing'])
        ->and($reports->has(VendorTicket::class))->toBeFalse();

    $this->artisan('teams:doctor')
        ->expectsOutputToContain('2 team-scoped model(s) are not safe to query.')
        ->assertFailed();
});

it('reports a listed model that is not scoped or has no table', function (): void {
    config()->set('teams.scoping.discover', []);
    config()->set('teams.scoping.models', [VendorTicket::class, 'App\\Models\\Missing']);

    $reports = collect(Teams::scopedModels())->keyBy('model');

    expect($reports[VendorTicket::class]->problems())->toBe(['does not use the ScopedToTeam trait'])
        ->and($reports['App\\Models\\Missing']->isHealthy())->toBeFalse();
});

it('says so when there is nothing to check', function (): void {
    config()->set('teams.scoping.discover', []);

    $this->artisan('teams:doctor')->expectsOutputToContain('No team-scoped models were found.')->assertSuccessful();
});
