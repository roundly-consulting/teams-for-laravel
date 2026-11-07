<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

/**
 * One team-scoped model as the doctor found it.
 */
final readonly class ScopedModelReport
{
    /**
     * @param  class-string  $model
     */
    public function __construct(
        public string $model,
        public string $table,
        public string $column,
        public bool $usesTrait,
        public bool $hasTable,
        public bool $hasColumn,
        public bool $hasIndex,
    ) {}

    public function isHealthy(): bool
    {
        return $this->usesTrait && $this->hasTable && $this->hasColumn && $this->hasIndex;
    }

    /**
     * What is wrong, one line each; empty when healthy.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return array_values(array_filter([
            $this->usesTrait ? null : 'does not use the ScopedToTeam trait',
            $this->hasTable ? null : "table [{$this->table}] does not exist",
            ! $this->hasTable || $this->hasColumn ? null : "column [{$this->column}] is missing",
            ! $this->hasColumn || $this->hasIndex ? null : "no index starts with [{$this->column}]",
        ]));
    }
}
