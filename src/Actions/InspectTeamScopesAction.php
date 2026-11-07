<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\DataTransferObjects\ScopedModelReport;
use RoundlyConsulting\Teams\Scoping\ScopedModelFinder;
use RoundlyConsulting\Teams\Scoping\TeamScope;

/**
 * The doctor: every team-scoped model, and whether its table has the team column and an
 * index starting with it. A scoped table without the column throws on every query; one
 * without the index scans the whole table — every tenant's rows — on every request.
 */
final readonly class InspectTeamScopesAction
{
    public function __construct(
        private ScopedModelFinder $finder,
    ) {}

    /**
     * @return list<ScopedModelReport>
     */
    public function execute(): array
    {
        $reports = [];

        foreach ($this->finder->find() as $class) {
            $reports[] = $this->inspect($class);
        }

        return $reports;
    }

    /**
     * @param  class-string  $class
     */
    private function inspect(string $class): ScopedModelReport
    {
        if (! is_subclass_of($class, Model::class)) {
            return new ScopedModelReport($class, '', 'team_id', false, false, false, false);
        }

        $model = new $class;
        $table = $model->getTable();
        $column = TeamScope::column($model);
        $schema = $model->getConnection()->getSchemaBuilder();

        $hasTable = $schema->hasTable($table);
        $hasColumn = $hasTable && $schema->hasColumn($table, $column);
        $hasIndex = $hasColumn && self::hasLeadingIndex($schema->getIndexes($table), $column);

        return new ScopedModelReport(
            model: $class,
            table: $table,
            column: $column,
            usesTrait: ScopedModelFinder::usesTrait($class),
            hasTable: $hasTable,
            hasColumn: $hasColumn,
            hasIndex: $hasIndex,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $indexes
     */
    private static function hasLeadingIndex(array $indexes, string $column): bool
    {
        foreach ($indexes as $index) {
            $columns = $index['columns'] ?? [];

            if (is_array($columns) && ($columns[0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }
}
