<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Scoping\TeamKey;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Broken\ColumnlessNote;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Broken\UnindexedNote;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Document;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\ScopedTicket;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Task;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\UlidTeam;
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

describe('the team key check', function () use ($fixtures): void {
    it('passes when the setting, the model and every migrated team column agree', function () use ($fixtures): void {
        config()->set('teams.scoping.discover', [$fixtures.'/Healthy']);

        $report = Teams::teamKeys();
        $type = TeamKey::type();

        expect($report->isHealthy())->toBeTrue()
            ->and($report->setting)->toBe($type)
            ->and($report->modelKeyType)->toBe($type)
            ->and(array_map(fn ($column): string => $column->name(), $report->columns))->toBe([
                'teams.id',
                'teams.parent_id',
                'team_invites.team_id',
                'team_members.team_id',
                'team_join_requests.team_id',
                'team_role_overrides.team_id',
                'documents.company_id',
                'projects.team_id',
                'vendor_tickets.team_id',
                'tasks.team_id',
            ])
            ->and(collect($report->columns)->every->isMigrated())->toBeTrue();

        $this->artisan('teams:doctor')
            ->expectsOutputToContain("The team key is {$type->value} everywhere.")
            ->assertSuccessful();
    });

    it('fails when a team subclass keys itself differently from the setting', function (): void {
        config()->set('teams.scoping.discover', []);
        config()->set('teams.models.team', UlidTeam::class);
        config()->set('teams.primary_key_type', 'uuid');

        $report = Teams::teamKeys();

        expect($report->isHealthy())->toBeFalse()
            ->and($report->modelKeyType)->toBe(KeyType::Ulid)
            ->and($report->problems()[0])->toBe('the team model ['.UlidTeam::class.'] keys by [ulid], but the primary_key_type setting is [uuid]');

        $this->artisan('teams:doctor')
            ->expectsOutputToContain('differs from teams.primary_key_type')
            ->assertFailed();
    });

    it('fails when the migrated team columns cannot hold the team key', function () use ($fixtures): void {
        config()->set('teams.scoping.discover', [$fixtures.'/Healthy']);

        // Whatever the suite migrated, claim the other kind of key: the setting and the
        // packaged model agree with each other, the database does not.
        $other = TeamKey::type() === KeyType::BigInt ? 'ulid' : 'bigint';
        config()->set('teams.primary_key_type', $other);

        $report = Teams::teamKeys();

        expect($report->setting->value)->toBe($other)
            ->and($report->modelKeyType->value)->toBe($other)
            ->and($report->problems())->toHaveCount(10)
            ->and($report->problems()[0])->toStartWith('column [teams.id] is [')
            ->and($report->problems()[0])->toEndWith("] but the team key is [{$other}]");

        $this->artisan('teams:doctor')
            ->expectsOutputToContain("cannot hold a {$other} key")
            ->assertFailed();
    });

    it('lists a team column that is not migrated without failing on it', function () use ($fixtures): void {
        config()->set('teams.scoping.discover', [$fixtures.'/Broken']);

        $report = Teams::teamKeys();
        $missing = collect($report->columns)->first(fn ($column): bool => $column->table === 'columnless_notes');

        expect($missing->isMigrated())->toBeFalse()
            ->and($missing->type)->toBeNull()
            ->and($report->isHealthy())->toBeTrue();

        $this->artisan('teams:doctor')->expectsOutputToContain('not migrated')->assertFailed(); // the scope check still fails it
    });
});
