<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;

final class DefineTeamRoleAction
{
    /**
     * Upsert a per-team role override. Idempotent on (team_id, key).
     */
    public function execute(DefineTeamRoleData $data): TeamRole
    {
        /** @var class-string<TeamRole> $model */
        $model = config('teams.models.team_role', TeamRole::class);

        /** @var TeamRole $role */
        $role = $model::query()->updateOrCreate(
            ['team_id' => $data->teamId, 'key' => $data->key],
            [
                'name' => $data->name,
                'permissions' => $this->permissionKeys($data->permissions),
                'description' => $data->description,
            ],
        );

        return $role;
    }

    /**
     * @param  list<string|Permission>  $permissions
     * @return list<string>
     */
    private function permissionKeys(array $permissions): array
    {
        return array_map(
            static fn (string|Permission $permission): string => $permission instanceof Permission
                ? $permission->key
                : $permission,
            $permissions,
        );
    }
}
