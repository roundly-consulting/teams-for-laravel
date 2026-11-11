<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * Keys the team by `teams.primary_key_type`, matching the column the migrations gave
 * `teams.id` for the same value: auto-increment on `bigint`, a time-ordered `uuid7` on `uuid`,
 * a lowercase `ulid` on `ulid` (the form Laravel's own {@see HasUlids} writes).
 *
 * Laravel's {@see HasUniqueStringIds} keys `getKeyType()`, `getIncrementing()`, `uniqueIds()`
 * and route-binding validation off the single `$usesUniqueIds` flag, so flipping that flag in
 * the initializer is the whole seam: on `bigint` the model behaves exactly as if it had never
 * used the trait.
 *
 * The signatures deliberately mirror {@see HasUlids} / {@see HasUuids} (no return type on
 * `newUniqueId()` or the initializer): a `teams.models.team` subclass that uses one of them itself — the way to
 * key teams by UUID/ULID before the setting existed — must keep compiling, and a typed
 * parent method would make it a fatal error.
 *
 * @phpstan-require-extends Model
 */
trait HasConfigurableKey
{
    use HasUniqueStringIds;

    /**
     * @return void
     */
    public function initializeHasUniqueStringIds()
    {
        $this->usesUniqueIds = $this->configuredKeyType() !== KeyType::BigInt;
    }

    /**
     * A time-ordered key of the configured type. Never called on `bigint` — the database
     * mints those.
     *
     * @return string
     */
    public function newUniqueId()
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? strtolower((string) Str::ulid())
            : (string) Str::uuid7();
    }

    /**
     * The configured key type of the teams table (`teams.primary_key_type`).
     */
    public function configuredKeyType(): KeyType
    {
        return KeyType::fromConfig('teams.primary_key_type');
    }

    /**
     * Whether a route or lookup key has the configured shape: a ULID on `ulid`, a UUID (any
     * version, so ids a host set explicitly still resolve) otherwise.
     *
     * @param  mixed  $value
     */
    protected function isValidUniqueId($value): bool
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? Str::isUlid($value)
            : Str::isUuid($value);
    }
}
