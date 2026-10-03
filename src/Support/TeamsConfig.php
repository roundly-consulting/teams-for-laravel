<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Carbon\CarbonInterval;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use Throwable;

/**
 * The strict readers behind every teams setting that is not a switch or a model.
 *
 * An absent (null) key means the documented default. A present value of the wrong shape
 * throws InvalidConfigurationException naming the key: a typo never falls back silently.
 * Before, `roles.provider` turned anything but `database` into the in-memory provider, an
 * `(int)` cast turned `TEAMS_INVITES_CODE_LENGTH=abc` into empty invite codes, and a
 * negative prune interval pruned memberships that had not even expired yet.
 *
 * @internal
 */
final class TeamsConfig
{
    public const string PROVIDER_ARRAY = 'array';

    public const string PROVIDER_DATABASE = 'database';

    /**
     * Seconds in a year — the longest role-cache TTL accepted.
     */
    private const int MAX_CACHE_TTL = 31_536_000;

    /**
     * Days in ten years — the widest expiring-membership window accepted.
     */
    private const int MAX_EXPIRING_DAYS = 3660;

    /**
     * The `team_invites.code` column is a 255-character string.
     */
    private const int MAX_CODE_LENGTH = 255;

    /**
     * `array` or `database`; absent means `array`.
     */
    public static function roleProvider(): string
    {
        return Config::oneOf('teams.roles.provider', [self::PROVIDER_ARRAY, self::PROVIDER_DATABASE], self::PROVIDER_ARRAY);
    }

    public static function ownerRole(): string
    {
        return self::string('teams.roles.owner', 'owner');
    }

    public static function adminRole(): string
    {
        return self::string('teams.roles.admin', 'admin');
    }

    public static function defaultRole(): string
    {
        return self::string('teams.roles.default', 'member');
    }

    /**
     * The role-cache store, or null for the default store.
     */
    public static function cacheStore(): ?string
    {
        return config('teams.roles.cache.store') === null ? null : Config::requireString('teams.roles.cache.store');
    }

    public static function cacheKey(): string
    {
        return self::string('teams.roles.cache.key', 'teams.roles');
    }

    /**
     * Seconds the role map stays cached: 1 to a year, 3600 when absent.
     */
    public static function cacheTtl(): int
    {
        return Config::integer('teams.roles.cache.ttl', 3600, min: 1, max: self::MAX_CACHE_TTL);
    }

    /**
     * Characters in a generated invite code: 1–255, 32 when absent.
     */
    public static function inviteCodeLength(): int
    {
        return Config::integer('teams.invites.code_length', 32, min: 1, max: self::MAX_CODE_LENGTH);
    }

    /**
     * The default invite lifetime — a positive interval, `7 days` when absent.
     */
    public static function inviteExpiresAfter(): CarbonInterval
    {
        return self::interval('teams.invites.expires_after', '7 days', allowZero: false);
    }

    /**
     * How long after expiry a membership is pruned — zero or positive, `30 days` when absent.
     */
    public static function membersPruneAfter(): CarbonInterval
    {
        return self::interval('teams.members.prune_after', '30 days', allowZero: true);
    }

    /**
     * How long after resolution a join request is pruned — zero or positive, `30 days` when absent.
     */
    public static function joinRequestsPruneAfter(): CarbonInterval
    {
        return self::interval('teams.join_requests.prune_after', '30 days', allowZero: true);
    }

    /**
     * The default expiring-membership window in days: 1–3660, 7 when absent.
     */
    public static function membersExpiringWithin(): int
    {
        return Config::integer('teams.members.expiring_within', 7, min: 1, max: self::MAX_EXPIRING_DAYS);
    }

    /**
     * The default approval quorum, or null when none is configured.
     */
    public static function approvalQuorum(): ?int
    {
        return config('teams.approvals.quorum') === null ? null : Config::integer('teams.approvals.quorum', 1, min: 1);
    }

    public static function gatePrefix(): string
    {
        return self::string('teams.gate.prefix', 'teams');
    }

    public static function gateOwnerAbility(): string
    {
        return self::string('teams.gate.owner_ability', 'owner');
    }

    /**
     * A string setting: `$default` when absent, otherwise a non-empty string or a throw.
     */
    private static function string(string $key, string $default): string
    {
        return config($key) === null ? $default : Config::requireString($key);
    }

    /**
     * A relative interval such as `7 days` or `P7D`: `$default` when absent; anything that
     * does not parse, or is negative (or zero, unless allowed), throws naming the key.
     */
    private static function interval(string $key, string $default, bool $allowZero): CarbonInterval
    {
        $value = config($key) ?? $default;

        try {
            $interval = is_string($value) && trim($value) !== '' ? CarbonInterval::make($value) : null;
        } catch (Throwable) {
            $interval = null;
        }

        $seconds = $interval === null ? -1.0 : $interval->totalSeconds;

        if ($interval === null || ($allowZero ? $seconds < 0 : $seconds <= 0)) {
            $expectation = $allowZero ? 'a zero or positive interval such as "30 days"' : 'a positive interval such as "7 days"';
            $given = match (true) {
                $value === '' => "''",
                is_string($value) => $value,
                is_int($value), is_float($value), is_bool($value) => var_export($value, true),
                default => get_debug_type($value),
            };

            throw new InvalidConfigurationException("Configuration value [{$key}] must be {$expectation}, [{$given}] given.");
        }

        return $interval;
    }
}
