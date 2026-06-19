<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

class GateDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('teams.gate.register', false);
    }
}
