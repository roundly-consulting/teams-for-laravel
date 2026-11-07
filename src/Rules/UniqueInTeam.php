<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\Scoping\TeamScope;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

/**
 * `unique`, per team: the value must not already exist in the team's rows of a
 * {@see ScopedToTeam} model. Two companies may both have a ticket "INV-1"; one company
 * may not have two.
 *
 *     'slug' => [UniqueInTeam::for(Project::class, 'slug')->ignore($project)],
 *
 * Checks the current team, or the one named with `->inTeam($team)` — required in
 * multi-team mode, where "this team" is ambiguous.
 */
final readonly class UniqueInTeam implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    private function __construct(
        private string $model,
        private string $column,
        private int|string|null $team = null,
        private mixed $ignore = null,
    ) {}

    /**
     * @param  class-string<Model>  $model  a model using ScopedToTeam
     */
    public static function for(string $model, string $column): self
    {
        return new self($model, $column);
    }

    /**
     * Check `$team`'s rows instead of the current team's.
     */
    public function inTeam(Team $team): self
    {
        if (! $team->exists) {
            throw TeamContextException::unsavedTeam();
        }

        return new self($this->model, $this->column, TeamContext::keyOf($team), $this->ignore);
    }

    /**
     * Skip one row — the one being updated — by model or primary key.
     */
    public function ignore(mixed $model): self
    {
        $key = $model instanceof Model ? $model->getKey() : $model;

        return new self($this->model, $this->column, $this->team, $key);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var Model $instance */
        $instance = new ($this->model);
        $team = $this->team ?? $this->currentTeam();

        $query = $instance->newQuery()
            ->withoutGlobalScope(TeamScope::class)
            ->where(TeamScope::qualifiedColumn($instance), $team)
            ->where($instance->qualifyColumn($this->column), $value);

        if ($this->ignore !== null) {
            $query->where($instance->getQualifiedKeyName(), '!=', $this->ignore);
        }

        if ($query->exists()) {
            $fail('teams::validation.unique_in_team')->translate();
        }
    }

    private function currentTeam(): int|string
    {
        $context = app(TeamContext::class);
        $keys = $context->keys() ?? throw TeamContextException::missing();

        if ($context->isMultiple() || count($keys) !== 1) {
            throw TeamContextException::ambiguous();
        }

        return $keys[0];
    }
}
