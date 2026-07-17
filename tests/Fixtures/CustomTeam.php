<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures;

use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host subclass of the packaged model, exactly as `teams.models.*` invites. It
 * inherits the table, so the only thing that can break is a convention the package
 * derives from the model's CLASS NAME — which is what these fixtures pin.
 */
final class CustomTeam extends Team
{
    /**
     * Required by `toHonourModelSwap`, and not decoration: asserting the concrete class of a
     * RETURNED object cannot tell a row really created as this class from one created as the
     * packaged Team and re-hydrated (permissions #31). Counting `created` events on this exact
     * class is the only independent oracle.
     */
    use CountsCreations;
}
