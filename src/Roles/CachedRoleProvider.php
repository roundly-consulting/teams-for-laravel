<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use Illuminate\Cache\Repository as ConcreteRepository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Support\TeamsConfig;

/**
 * Caches an inner RoleProvider's role map in the shared cache and flushes it on
 * every mutation. Tag support is used only when the configured store supports tags.
 *
 * The map read from the cache is memoised for the lifetime of the instance (one
 * request or job — the container binds the provider scoped), and a refill always
 * comes from the inner provider, which must not memoise.
 */
final class CachedRoleProvider implements RoleProvider
{
    private const TAG = 'teams.roles';

    /** @var array<string, Role>|null */
    private ?array $roles = null;

    public function __construct(
        private readonly RoleProvider $inner,
    ) {}

    /** @param list<string|Permission> $permissions */
    public function register(string $key, string $name, array $permissions = [], string $description = ''): Role
    {
        $role = $this->inner->register($key, $name, $permissions, $description);

        $this->flush();

        return $role;
    }

    public function find(string $key): ?Role
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, Role> */
    public function all(): array
    {
        if ($this->roles !== null) {
            return $this->roles;
        }

        /** @var array<string, Role> $roles */
        $roles = $this->cache()->remember($this->key(), $this->ttl(), fn (): array => $this->inner->all());

        return $this->roles = $roles;
    }

    /**
     * Forget the memoised map and the shared cache entry.
     *
     * @internal called on every role mutation and when a role definition changes
     */
    public function flush(): void
    {
        $this->roles = null;

        if ($this->inner instanceof DatabaseRoleProvider) {
            $this->inner->flush();
        }

        $store = $this->store();
        $taggable = $this->taggable($store);

        if ($taggable !== null) {
            $taggable->tags(self::TAG)->flush();

            return;
        }

        $store->forget($this->key());
    }

    private function cache(): Repository
    {
        $store = $this->store();
        $taggable = $this->taggable($store);

        if ($taggable !== null) {
            return $taggable->tags(self::TAG);
        }

        return $store;
    }

    private function store(): Repository
    {
        return Cache::store(TeamsConfig::cacheStore());
    }

    /**
     * Return the store as a concrete, tag-capable repository when its backing
     * store supports tags, otherwise null.
     */
    private function taggable(Repository $store): ?ConcreteRepository
    {
        if ($store instanceof ConcreteRepository && $store->getStore() instanceof TaggableStore) {
            return $store;
        }

        return null;
    }

    private function key(): string
    {
        return TeamsConfig::cacheKey();
    }

    private function ttl(): int
    {
        return TeamsConfig::cacheTtl();
    }
}
