<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\TeamsManager;

final class ListPermissionsCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:permissions';

    /** @var string */
    protected $description = 'List the registered permissions and their groups';

    public function handle(TeamsManager $teams): int
    {
        $permissions = $teams->permissions()->all();

        if ($permissions === []) {
            $this->info('No permissions are registered.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($permissions as $permission) {
            $rows[] = [
                $permission->key,
                $permission->name,
                $permission->group,
            ];
        }

        $this->table(['Key', 'Name', 'Group'], $rows);

        return self::SUCCESS;
    }
}
