<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests;

use RoundlyConsulting\Teams\Tests\Fixtures\CustomInvite;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomJoinRequest;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomMember;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeam;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeamRole;

/**
 * The suite's base case with all five `teams.models.*` keys already pointed at host
 * subclasses BEFORE the providers boot — the only window a real host has, since
 * `config/teams.php` is read at boot.
 *
 * `tests/Feature/ConfiguredModelsTest.php` covers the same seams from a `beforeEach`, i.e.
 * in the test body. That was audited rather than assumed: this provider hangs **no**
 * boot-time wiring on the configured model classes (its one `Event::listen` is on an
 * approvals event, not a model), so unlike reviews' equivalent that test is genuinely not
 * vacuous — it re-reads config on each resolver call and the reads are real. It is kept.
 *
 * This case exists for what a body-time swap cannot reach anyway: the state a host actually
 * boots in, plus `CountsCreations` as an independent oracle that rows are created **as** the
 * host's class rather than merely returning something that passes `instanceof`.
 *
 * `defineEnvironment()` is deliberately NOT overridden. PackageTestCase does its whole job
 * there — `DriverMatrix::configure()` + `configBeforeBoot()` + the model swaps — so an
 * override without `parent::` decapitates the base case silently: no error, no red, and the
 * pgsql leg quietly running sqlite.
 */
abstract class SwappedModelsTestCase extends TestCase
{
    /**
     * Note `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
     * the base case's options/connections cache wiring, and every test here would read
     * memoised state from its predecessor.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'teams.models.team' => CustomTeam::class,
            'teams.models.member' => CustomMember::class,
            'teams.models.invite' => CustomInvite::class,
            'teams.models.team_role' => CustomTeamRole::class,
            'teams.models.join_request' => CustomJoinRequest::class,
        ]);
    }
}
