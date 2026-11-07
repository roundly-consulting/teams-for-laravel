<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy;

use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\VendorTicket;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

/**
 * The host subclasses another package's model and adds the trait.
 */
final class ScopedTicket extends VendorTicket
{
    use ScopedToTeam;
}
