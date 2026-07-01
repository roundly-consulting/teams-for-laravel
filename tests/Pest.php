<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Tests\GateDisabledTestCase;
use RoundlyConsulting\Teams\Tests\TestCase;

uses(TestCase::class)->in(
    __DIR__.'/Unit',
    __DIR__.'/Cross',
    __DIR__.'/Traits',
    __DIR__.'/Feature/Commands',
    __DIR__.'/Feature/BladeDirectivesTest.php',
    __DIR__.'/Feature/GateIntegrationTest.php',
    __DIR__.'/Feature/RoleProviderSwitchTest.php',
    __DIR__.'/Feature/TeamsFacadeTest.php',
    __DIR__.'/Feature/PerTeamRolesTest.php',
    __DIR__.'/Feature/OwnerAbilityTest.php',
    __DIR__.'/Feature/JoinRequestFlowTest.php',
    __DIR__.'/Feature/NotificationBridgeTest.php',
    __DIR__.'/Feature/TeamsFakeTest.php',
    __DIR__.'/Feature/AcceptInviteStubTest.php',
    __DIR__.'/ArchTest.php',
    __DIR__.'/InviteTest.php',
    __DIR__.'/MemberTest.php',
    __DIR__.'/RolesTest.php',
    __DIR__.'/RoleTest.php',
    __DIR__.'/TeamTest.php',
);

uses(GateDisabledTestCase::class)->in(__DIR__.'/Feature/GateDisabled');
