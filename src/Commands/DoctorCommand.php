<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `php artisan teams:doctor` — lists the team-scoped models and fails (exit 1) when one
 * lacks its team column or an index on it. Cheap enough for CI.
 */
final class DoctorCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:doctor';

    /** @var string */
    protected $description = 'Check every team-scoped model has its team column and an index on it';

    public function handle(TeamsManager $teams): int
    {
        $reports = $teams->scopedModels();

        if ($reports === []) {
            $this->info('No team-scoped models were found.');

            return self::SUCCESS;
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

        $failing = count(array_filter($reports, static fn ($report): bool => ! $report->isHealthy()));

        if ($failing > 0) {
            $this->error("{$failing} team-scoped model(s) are not safe to query.");

            return self::FAILURE;
        }

        $this->info('Every team-scoped model has its team column and index.');

        return self::SUCCESS;
    }
}
