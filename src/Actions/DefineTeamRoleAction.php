<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Support\TeamRoleModel;

final readonly class DefineTeamRoleAction
{
    /**
     * Upsert a per-team role override. Idempotent on (team_id, key); an override
     * that was soft-deleted is restored and redefined rather than re-inserted
     * (the unique index covers trashed rows).
     */
    public function execute(DefineTeamRoleData $data): TeamRole
    {
        /** @var TeamRole $role */
        $role = TeamRoleModel::query()
            ->withTrashed()
            ->firstOrNew(['team_id' => $data->teamId, 'key' => $data->key]);

        $role->fill([
            'name' => $data->name,
            'permissions' => $this->permissionKeys($data->permissions),
            'description' => $data->description,
        ]);

        if ($role->trashed()) {
            $role->restore();
        } else {
            $role->save();
        }

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
