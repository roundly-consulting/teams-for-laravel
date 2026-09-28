<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use RoundlyConsulting\Teams\Actions\DefineTeamRoleAction;
use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::for($team)->roles()` — the team's effective roles: the global roles merged
 * with this team's overrides (overrides win on key when `teams.roles.per_team` is on).
 */
final readonly class TeamRoles
{
    public function __construct(
        private TeamsManager $manager,
        private Team $team,
    ) {}

    /**
     * Upsert a per-team role override. Idempotent on (team, key).
     *
     * @param  list<string|Permission>  $permissions
     */
    public function define(string $key, string $name, array $permissions = [], string $description = ''): TeamRole
    {
        return $this->manager->perform(
            TeamOperation::DefineRole,
            DefineTeamRoleAction::class,
            fn (DefineTeamRoleAction $action): TeamRole => $action->execute(new DefineTeamRoleData(
                teamId: (int) $this->team->getKey(),
                key: $key,
                name: $name,
                permissions: $permissions,
                description: $description,
            )),
            ['team' => $this->team, 'key' => $key],
        );
    }

    /**
     * @return array<string, Role>
     */
    public function all(): array
    {
        return app(TeamRoleResolver::class)->all($this->team);
    }

    public function find(string $key): ?Role
    {
        return app(TeamRoleResolver::class)->resolve($this->team, $key);
    }
}
