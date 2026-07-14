<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Support\TeamRoleModel;

final class DefineTeamRoleAction
{
    /**
     * Upsert a per-team role override. Idempotent on (team_id, key).
     */
    public function execute(DefineTeamRoleData $data): TeamRole
    {
        $role = TeamRoleModel::query()->updateOrCreate(
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
