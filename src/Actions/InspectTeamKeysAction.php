<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Teams\DataTransferObjects\TeamKeyColumn;
use RoundlyConsulting\Teams\DataTransferObjects\TeamKeyReport;
use RoundlyConsulting\Teams\Scoping\ColumnKeyType;
use RoundlyConsulting\Teams\Scoping\ScopedModelFinder;
use RoundlyConsulting\Teams\Scoping\TeamKey;
use RoundlyConsulting\Teams\Scoping\TeamScope;
use RoundlyConsulting\Teams\Support\InviteModel;
use RoundlyConsulting\Teams\Support\JoinRequestModel;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamHierarchy;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Support\TeamRoleModel;
use RoundlyConsulting\Teams\Support\TeamsSchema;

/**
 * The doctor's key check: `teams.primary_key_type`, the key the configured team model really
 * writes, and the type of every column holding a team key — `teams.id`, `parent_id`, the
 * `team_id` of the package's tables and the team column of every scoped model. A mismatch
 * breaks every foreign key or insert on a strict engine, and SQLite's type affinity hides it.
 */
final readonly class InspectTeamKeysAction
{
    public function __construct(
        private ScopedModelFinder $finder,
    ) {}

    public function execute(): TeamKeyReport
    {
        $expected = TeamKey::type();
        $team = TeamModel::new();

        $columns = [
            self::inspect($team, $team->getKeyName(), $expected),
            self::inspect($team, TeamHierarchy::PARENT_COLUMN, $expected),
            self::inspect(InviteModel::new(), 'team_id', $expected),
            self::inspect(MemberModel::new(), 'team_id', $expected),
            self::inspect(JoinRequestModel::new(), 'team_id', $expected),
            self::inspect(TeamRoleModel::new(), 'team_id', $expected),
        ];

        foreach ($this->finder->find() as $class) {
            if (is_subclass_of($class, Model::class) && ScopedModelFinder::usesTrait($class)) {
                $model = new $class;
                $columns[] = self::inspect($model, TeamScope::column($model), $expected);
            }
        }

        return new TeamKeyReport(
            setting: TeamsSchema::keyType(),
            model: $team::class,
            modelKeyType: $expected,
            columns: $columns,
        );
    }

    private static function inspect(Model $model, string $column, KeyType $expected): TeamKeyColumn
    {
        $table = $model->getTable();
        $schema = $model->getConnection()->getSchemaBuilder();

        $found = $schema->hasTable($table)
            ? collect($schema->getColumns($table))->first(static fn (array $candidate): bool => $candidate['name'] === $column)
            : null;

        if (! is_array($found)) {
            return new TeamKeyColumn($table, $column, null, true);
        }

        return new TeamKeyColumn($table, $column, ColumnKeyType::describe($found), ColumnKeyType::holds($expected, $found));
    }
}
