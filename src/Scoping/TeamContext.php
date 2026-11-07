<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Collection as BaseCollection;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * The current team(s) of this request, job or command.
 *
 * The team keys live in Laravel's HIDDEN Context, not in this object. That is the whole
 * queue story: Laravel serialises the context into every job payload dispatched while it
 * is set and hydrates it — after flushing — when the job runs, so a job restores exactly
 * the team(s) of the code that dispatched it, and a job dispatched without one runs
 * without one. Hidden, so the keys never reach a log line. A fresh console process or a
 * scheduled task starts with an empty Context, hence no team.
 *
 * What stays in this object is per-process only: the loaded Team models (a cache keyed by
 * the context it was loaded for) and the `withoutTeamScope()` bypass depth, which must
 * never travel into a job. The binding is SCOPED, so Octane and the queue worker start
 * every request and job with a fresh one.
 *
 * @internal
 */
final class TeamContext
{
    /** Hidden Context key holding the current team keys (a list). */
    public const string KEYS = 'roundly-teams.context.keys';

    /** Hidden Context key holding whether the keys are a multi-team set. */
    public const string MULTIPLE = 'roundly-teams.context.multiple';

    private int $bypassDepth = 0;

    private ?string $loadedFor = null;

    /** @var Collection<int, Team> */
    private Collection $loaded;

    public function __construct(
        private readonly Container $container,
    ) {
        $this->loaded = new Collection;
    }

    /**
     * The team keys in context, or null when no context is set. An empty list is a
     * multi-team context holding no team — it scopes to nothing.
     *
     * @return list<int|string>|null
     */
    public function keys(): ?array
    {
        $keys = $this->repository()->getHidden(self::KEYS);

        if (! is_array($keys)) {
            return null;
        }

        $valid = [];

        foreach ($keys as $key) {
            if (is_int($key) || (is_string($key) && $key !== '')) {
                $valid[] = $key;
            }
        }

        return $valid;
    }

    public function has(): bool
    {
        return $this->keys() !== null;
    }

    public function isMultiple(): bool
    {
        return $this->has() && $this->repository()->getHidden(self::MULTIPLE) === true;
    }

    /**
     * Whether a write for the team keyed `$team` is inside the context.
     */
    public function allows(int|string $team): bool
    {
        foreach ($this->keys() ?? [] as $key) {
            if ((string) $key === (string) $team) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  BaseCollection<int, Team>  $teams  saved teams, already de-duplicated
     */
    public function set(BaseCollection $teams, bool $multiple): void
    {
        /** @var list<int|string> $keys */
        $keys = $teams->map(static fn (Team $team): int|string => self::keyOf($team))->values()->all();

        $this->repository()->addHidden(self::KEYS, $keys);
        $this->repository()->addHidden(self::MULTIPLE, $multiple);

        $this->loaded = new Collection($teams->values()->all());
        $this->loadedFor = self::fingerprint($keys);
    }

    public function clear(): void
    {
        $this->repository()->forgetHidden(self::KEYS);
        $this->repository()->forgetHidden(self::MULTIPLE);

        $this->loaded = new Collection;
        $this->loadedFor = null;
    }

    /**
     * The single current team, or null without a context or in multi-team mode.
     */
    public function current(): ?Team
    {
        return $this->isMultiple() ? null : $this->teams()->first();
    }

    /**
     * Every team in context, in context order. Loaded lazily — a job restores bare keys —
     * and cached for the keys it was loaded for.
     *
     * @return Collection<int, Team>
     */
    public function teams(): Collection
    {
        $keys = $this->keys();

        if ($keys === null || $keys === []) {
            return new Collection;
        }

        $fingerprint = self::fingerprint($keys);

        if ($this->loadedFor !== $fingerprint) {
            $found = TeamModel::query()->whereKey($keys)->get()->keyBy(static fn (Team $team): string => (string) $team->getKey());

            $ordered = [];

            foreach ($keys as $key) {
                $team = $found->get((string) $key);

                if ($team !== null) {
                    $ordered[] = $team;
                }
            }

            $this->loaded = new Collection($ordered);
            $this->loadedFor = $fingerprint;
        }

        return $this->loaded;
    }

    public function snapshot(): ContextSnapshot
    {
        return new ContextSnapshot($this->keys(), $this->isMultiple());
    }

    public function restore(ContextSnapshot $snapshot): void
    {
        if ($snapshot->keys === null) {
            $this->clear();

            return;
        }

        $this->repository()->addHidden(self::KEYS, $snapshot->keys);
        $this->repository()->addHidden(self::MULTIPLE, $snapshot->multiple);
    }

    /**
     * Run `$callback` with team scoping off: scoped reads see every team and scoped
     * writes go unchecked. Per process — a job dispatched inside does not inherit it.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function bypass(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * The key a team is scoped by.
     */
    public static function keyOf(Team $team): int|string
    {
        $key = $team->getKey();

        return is_int($key) || is_string($key) ? $key : (string) $key;
    }

    /**
     * Resolved per call, never held: Laravel's Context repository is scoped too, and a
     * held copy would outlive the request or job it belongs to.
     */
    private function repository(): Repository
    {
        return $this->container->make(Repository::class);
    }

    /**
     * @param  list<int|string>  $keys
     */
    private static function fingerprint(array $keys): string
    {
        return implode("\0", array_map(static fn (int|string $key): string => gettype($key).':'.$key, $keys));
    }
}
