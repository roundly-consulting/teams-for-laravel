<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

/**
 * The suite's base case with the package's gate registration switched off before boot —
 * the only window that matters, since the provider decides whether to register its gate
 * abilities at boot.
 *
 * This used to override `defineEnvironment()` (with `parent::`, so it was not the silent
 * decapitation step 3a warns about). It moves to `configBeforeBoot()` anyway: that is the
 * hook the base case exposes for exactly this, and it removes the standing hazard that a
 * later edit drops the `parent::` call and quietly leaves DriverMatrix unconfigured — a
 * "pgsql" leg running sqlite, with no error and no red.
 *
 * Note `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard the
 * base case's options/connections cache wiring — the same decapitation one level down.
 */
class GateDisabledTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'teams.gate.register' => false,
        ]);
    }
}
