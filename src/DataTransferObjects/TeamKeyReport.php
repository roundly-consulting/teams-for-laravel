<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Teams\Models\Team;

/**
 * The team key as the doctor found it: `teams.primary_key_type`, the key the configured team
 * model really writes, and every database column holding one. A column that is not migrated
 * yet is listed but is no problem.
 */
final readonly class TeamKeyReport
{
    /**
     * @param  class-string<Team>  $model
     * @param  list<TeamKeyColumn>  $columns
     */
    public function __construct(
        public KeyType $setting,
        public string $model,
        public KeyType $modelKeyType,
        public array $columns,
    ) {}

    public function isHealthy(): bool
    {
        return $this->problems() === [];
    }

    /**
     * What disagrees, one line each; empty when healthy.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->setting !== $this->modelKeyType) {
            $problems[] = sprintf(
                'the team model [%s] keys by [%s], but the primary_key_type setting is [%s]',
                $this->model,
                $this->modelKeyType->value,
                $this->setting->value,
            );
        }

        foreach ($this->columns as $column) {
            if ($column->isMigrated() && ! $column->matches) {
                $problems[] = sprintf(
                    'column [%s] is [%s] but the team key is [%s]',
                    $column->name(),
                    $column->type,
                    $this->modelKeyType->value,
                );
            }
        }

        return $problems;
    }
}
