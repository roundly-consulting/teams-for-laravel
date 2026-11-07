<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Collection;
use RoundlyConsulting\Teams\Exceptions\TeamHierarchyException;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Walks and guards the team tree.
 *
 * Every walk is bounded by `teams.nesting.max_depth` and remembers what it has visited,
 * so even rows corrupted into a loop behind the guard's back (a raw query) end the walk
 * instead of spinning forever.
 *
 * @internal
 */
final class TeamHierarchy
{
    public const string PARENT_COLUMN = 'parent_id';

    /**
     * Reject a parent change that nesting is off for, that closes a cycle, or that puts
     * any team of the moved subtree deeper than `teams.nesting.max_depth`. Runs on every
     * team save; a save that does not touch the parent costs nothing.
     */
    public static function guard(Team $team): void
    {
        if (! $team->isDirty(self::PARENT_COLUMN)) {
            return;
        }

        $parentKey = $team->getAttribute(self::PARENT_COLUMN);

        // Detaching is always allowed — it is how a host leaves nesting behind.
        if ($parentKey === null || $parentKey === '') {
            return;
        }

        if (! TeamsConfig::nestingEnabled()) {
            throw TeamHierarchyException::nestingDisabled();
        }

        $maxDepth = TeamsConfig::nestingMaxDepth();
        $own = $team->exists ? (string) $team->getKey() : null;

        // The new parent's depth, walking up through trashed teams too: a soft-deleted
        // ancestor still links the chain, and restoring it must not reveal a cycle.
        $parentDepth = 0;
        $cursor = $parentKey;
        $seen = [];

        while ($cursor !== null && $cursor !== '') {
            $key = self::stringKey($cursor);

            if ($key === $own) {
                throw TeamHierarchyException::cycle($own);
            }

            if (isset($seen[$key]) || $parentDepth > $maxDepth) {
                throw TeamHierarchyException::tooDeep($maxDepth);
            }

            $seen[$key] = true;
            $cursor = self::parentKeyOf($key);

            if ($cursor !== null && $cursor !== '') {
                $parentDepth++;
            }
        }

        $depth = $parentDepth + 1;
        $height = $own === null ? 0 : self::heightBelow($own, $maxDepth - $depth);

        if ($depth + $height > $maxDepth) {
            throw TeamHierarchyException::tooDeep($maxDepth);
        }
    }

    /**
     * Ancestors nearest first, stopping at a soft-deleted one: a trashed team grants
     * nothing and roots nothing.
     *
     * @return Collection<int, Team>
     */
    public static function ancestors(Team $team): Collection
    {
        $ancestors = new Collection;

        if (! TeamsConfig::nestingEnabled()) {
            return $ancestors;
        }

        $seen = [(string) $team->getKey() => true];
        $parentKey = $team->getAttribute(self::PARENT_COLUMN);
        $limit = TeamsConfig::nestingMaxDepth();

        while (($parentKey !== null && $parentKey !== '') && $ancestors->count() < $limit) {
            $key = self::stringKey($parentKey);

            if (isset($seen[$key])) {
                break;
            }

            $seen[$key] = true;

            /** @var Team|null $parent */
            $parent = TeamModel::query()->find($parentKey);

            if ($parent === null) {
                break;
            }

            $ancestors->push($parent);
            $parentKey = $parent->getAttribute(self::PARENT_COLUMN);
        }

        return $ancestors;
    }

    /**
     * Every team below `$team`, level by level (one query per level).
     *
     * @return Collection<int, Team>
     */
    public static function descendants(Team $team): Collection
    {
        $descendants = new Collection;

        if (! TeamsConfig::nestingEnabled() || ! $team->exists) {
            return $descendants;
        }

        $seen = [(string) $team->getKey() => true];
        $frontier = [$team->getKey()];
        $levels = 0;
        $limit = TeamsConfig::nestingMaxDepth();

        while ($frontier !== [] && $levels < $limit) {
            $children = TeamModel::query()->whereIn(self::PARENT_COLUMN, $frontier)->get();
            $frontier = [];

            foreach ($children as $child) {
                $key = (string) $child->getKey();

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $descendants->push($child);
                $frontier[] = $child->getKey();
            }

            $levels++;
        }

        return $descendants;
    }

    /**
     * How many levels of (trashed or live) teams hang below `$key`, counting at most
     * `$budget + 1` — enough to know the subtree no longer fits.
     */
    private static function heightBelow(string $key, int $budget): int
    {
        $height = 0;
        $frontier = [$key];
        $seen = [$key => true];

        while ($frontier !== [] && $height <= $budget) {
            $children = TeamModel::query()->withTrashed()->whereIn(self::PARENT_COLUMN, $frontier)->pluck(TeamModel::new()->getKeyName());
            $frontier = [];

            foreach ($children as $child) {
                $childKey = self::stringKey($child);

                if (isset($seen[$childKey])) {
                    continue;
                }

                $seen[$childKey] = true;
                $frontier[] = $childKey;
            }

            if ($frontier !== []) {
                $height++;
            }
        }

        return $height;
    }

    private static function parentKeyOf(string $key): mixed
    {
        return TeamModel::query()->withTrashed()->whereKey($key)->value(self::PARENT_COLUMN);
    }

    private static function stringKey(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }
}
