<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\Roles\Roles;

final class ListRolesCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:roles';

    /** @var string */
    protected $description = 'List the registered team roles and their permissions';

    public function handle(): int
    {
        $roles = Roles::all();

        if ($roles === []) {
            $this->info('No roles are registered.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($roles as $role) {
            $rows[] = [
                $role->key,
                $role->name,
                implode(', ', $role->permissions),
            ];
        }

        $this->table(['Key', 'Name', 'Permissions'], $rows);

        return self::SUCCESS;
    }
}
