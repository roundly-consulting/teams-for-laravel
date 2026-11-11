<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

/**
 * One database column holding a team key (`teams.id`, a `team_id`, `parent_id`, a scoped
 * table's team column) as the doctor found it.
 */
final readonly class TeamKeyColumn
{
    /**
     * @param  string|null  $type  the column's database type; null when the table or column is not migrated
     */
    public function __construct(
        public string $table,
        public string $column,
        public ?string $type,
        public bool $matches,
    ) {}

    public function name(): string
    {
        return "{$this->table}.{$this->column}";
    }

    public function isMigrated(): bool
    {
        return $this->type !== null;
    }
}
