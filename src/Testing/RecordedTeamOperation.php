<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Enums\TeamOperation;

/**
 * One operation recorded by {@see TeamsFake}: what ran, with which models, and what
 * it returned.
 *
 * Context keys: `team`; `name`, `owner` (create, transfer); `member`, `role`
 * (members); `invite`, `email` (invites); `requester`, `request`, `by` (join
 * requests); `key` (role definitions); `days` (expiry notifications).
 */
final readonly class RecordedTeamOperation
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public TeamOperation $operation,
        public array $context,
        public mixed $result,
    ) {}

    /**
     * The model recorded under the context key, if any.
     */
    public function model(string $key): ?Model
    {
        $value = $this->context[$key] ?? null;

        return $value instanceof Model ? $value : null;
    }

    /**
     * Whether the context key holds the same model as `$model`.
     */
    public function involves(string $key, Model $model): bool
    {
        return $this->model($key)?->is($model) === true;
    }

    /**
     * The string recorded under the context key, if any.
     */
    public function string(string $key): ?string
    {
        $value = $this->context[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
