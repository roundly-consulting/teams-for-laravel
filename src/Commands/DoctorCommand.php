<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\DataTransferObjects\ScopedModelReport;
use RoundlyConsulting\Teams\DataTransferObjects\TeamKeyReport;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `php artisan teams:doctor` — checks the team key type (`teams.primary_key_type`, the team
 * model and every column holding a team key agree) and lists the team-scoped models, failing
 * (exit 1) when anything disagrees or a scoped model lacks its team column or an index on
 * it. Cheap enough for CI.
 */
final class DoctorCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:doctor';

    /** @var string */
    protected $description = 'Check the team key type and every team-scoped model\'s team column and index';

    public function handle(TeamsManager $teams): int
    {
        $keysHealthy = $this->reportKeys($teams->teamKeys());
        $scopesHealthy = $this->reportScopes($teams->scopedModels());

        return $keysHealthy && $scopesHealthy ? self::SUCCESS : self::FAILURE;
    }

    private function reportKeys(TeamKeyReport $report): bool
    {
        $rows = [
            ['teams.primary_key_type', $report->setting->value, 'OK'],
            [
                $report->model,
                $report->modelKeyType->value,
                $report->setting === $report->modelKeyType ? 'OK' : 'differs from teams.primary_key_type',
            ],
        ];

        foreach ($report->columns as $column) {
            $rows[] = [
                $column->name(),
                $column->type ?? '-',
                match (true) {
                    ! $column->isMigrated() => 'not migrated',
                    $column->matches => 'OK',
                    default => "cannot hold a {$report->modelKeyType->value} key",
                },
            ];
        }

        $this->table(['Team key', 'Type', 'Status'], $rows);

        if (! $report->isHealthy()) {
            foreach ($report->problems() as $problem) {
                $this->error($problem);
            }

            return false;
        }

        $this->info("The team key is {$report->modelKeyType->value} everywhere.");

        return true;
    }

    /**
     * @param  list<ScopedModelReport>  $reports
     */
    private function reportScopes(array $reports): bool
    {
        if ($reports === []) {
            $this->info('No team-scoped models were found.');

            return true;
        }

        $rows = [];

        foreach ($reports as $report) {
            $rows[] = [
                $report->model,
                $report->table,
                $report->column,
                $report->isHealthy() ? 'OK' : implode('; ', $report->problems()),
            ];
        }

        $this->table(['Model', 'Table', 'Column', 'Status'], $rows);

        $failing = count(array_filter($reports, static fn (ScopedModelReport $report): bool => ! $report->isHealthy()));

        if ($failing > 0) {
            $this->error("{$failing} team-scoped model(s) are not safe to query.");

            return false;
        }

        $this->info('Every team-scoped model has its team column and index.');

        return true;
    }
}
