<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Orchestra\Testbench\TestCase;
use RoundlyConsulting\Teams\Testing\Concerns\InteractsWithTeams;

/**
 * A host test case using the testing helper — here so static analysis checks the trait.
 */
abstract class ActingInTeamCase extends TestCase
{
    use InteractsWithTeams;
}
