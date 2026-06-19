<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use Illuminate\Cache\Repository as ConcreteRepository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

/**
 * Caches an inner RoleProvider's role map and flushes it on every mutation.
 * Tag support is used only when the configured store supports tags.
 */
final class CachedRoleProvider implements RoleProvider
{
    private const TAG = 'teams.roles';

    public function __construct(
        private readonly RoleProvider $inner,
    ) {}

    /** @param list<string|Permission> $permissions */
    public function register(string $key, string $name, array $permissions = []): Role
    {
        $role = $this->inner->register($key, $name, $permissions);

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
        /** @var array<string, Role> $roles */
        $roles = $this->cache()->remember($this->key(), $this->ttl(), fn (): array => $this->inner->all());

        return $roles;
    }

    private function flush(): void
    {
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
        /** @var string|null $name */
        $name = config('teams.roles.cache.store');

        return Cache::store($name);
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
        /** @var string $key */
        $key = config('teams.roles.cache.key', 'teams.roles');

        return $key;
    }

    private function ttl(): int
    {
        return (int) config('teams.roles.cache.ttl', 3600);
    }
}
