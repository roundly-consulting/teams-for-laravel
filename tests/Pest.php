<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Tests\GateDisabledTestCase;
use RoundlyConsulting\Teams\Tests\SwappedModelsTestCase;
use RoundlyConsulting\Teams\Tests\TestCase;

// `Feature` binds as a DIRECTORY rather than the file-by-file list this used to carry. That
// list was a standing trap: a new test file under Feature/ that nobody remembered to add ran
// with NO base case — no app, no database — which is how passkeys' ArchTest came to assert
// nothing at all. The list only existed because Feature/GateDisabled needed a different base
// case, and Pest binds per directory; that directory has moved to tests/GateDisabled, so the
// blanket bind is safe. (Pest errors loudly on a blanket-vs-specific collision rather than
// silently picking one — verified, not assumed.)
//
// ArchTest.php is bound by FILE path — `uses()->in()` accepts one — because
// `swappableModelsAreNotFinal` reads the five `teams.models.*` config defaults and so needs
// the app booted.
uses(TestCase::class)->in(
    __DIR__.'/Unit',
    __DIR__.'/Cross',
    __DIR__.'/Traits',
    __DIR__.'/Feature',
    __DIR__.'/ArchTest.php',
    __DIR__.'/InviteTest.php',
    __DIR__.'/MemberTest.php',
    __DIR__.'/RolesTest.php',
    __DIR__.'/RoleTest.php',
    __DIR__.'/TeamTest.php',
);

uses(GateDisabledTestCase::class)->in(__DIR__.'/GateDisabled');

// The model-swap proofs need every `teams.models.*` key pointed at a host subclass BEFORE the
// providers boot, so they run on their own base case in their own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedModelsTestCase::class)->in(__DIR__.'/SwappedModels');
