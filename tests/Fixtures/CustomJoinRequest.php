<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures;

use RoundlyConsulting\Teams\Models\JoinRequest;

/**
 * A host subclass of the packaged model, exactly as `teams.models.*` invites. It
 * inherits the table, so the only thing that can break is a convention the package
 * derives from the model's CLASS NAME — which is what these fixtures pin.
 */
final class CustomJoinRequest extends JoinRequest {}
