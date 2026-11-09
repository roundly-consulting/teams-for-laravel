<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\PermissionsBridge;

use RoundlyConsulting\Teams\Tests\TestCase;

/**
 * permissions-for-laravel booted with scoped assignments on, and `teams.permissions.bridge` on.
 */
abstract class PermissionsBridgeTestCase extends TestCase
{
    protected function loadsPermissions(): bool
    {
        return true;
    }

    protected function permissionsBridge(): bool
    {
        return true;
    }
}
