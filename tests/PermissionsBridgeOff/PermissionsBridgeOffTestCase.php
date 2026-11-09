<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\PermissionsBridgeOff;

use RoundlyConsulting\Teams\Tests\TestCase;

/**
 * permissions-for-laravel installed and booted (assignments on), `teams.permissions.bridge` off:
 * what every host that has both packages gets until it opts in.
 */
abstract class PermissionsBridgeOffTestCase extends TestCase
{
    protected function loadsPermissions(): bool
    {
        return true;
    }

    protected function permissionsBridge(): bool
    {
        return false;
    }
}
