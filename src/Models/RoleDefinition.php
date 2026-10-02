<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Database\Factories\RoleDefinitionFactory;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property Collection<int, string> $permissions
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class RoleDefinition extends Model
{
    /** @use HasFactory<RoleDefinitionFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var string */
    protected $table = 'team_roles';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'collection',
        ];
    }

    protected static function newFactory(): RoleDefinitionFactory
    {
        return RoleDefinitionFactory::new();
    }

    /**
     * Any change to a definition — through the provider or straight through this
     * model (an admin UI) — invalidates the role map this process memoised and the
     * shared role cache, so a revoked permission stops granting at once.
     */
    protected static function booted(): void
    {
        $flush = static function (): void {
            $provider = app(RoleProvider::class);

            if ($provider instanceof DatabaseRoleProvider || $provider instanceof CachedRoleProvider) {
                $provider->flush();
            }
        };

        self::saved($flush);
        self::deleted($flush);
        self::restored($flush);
    }
}
