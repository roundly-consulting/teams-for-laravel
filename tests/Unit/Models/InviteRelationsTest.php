<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Teams\Models\Invite;

it('has an invited-by morph relationship', function () {
    expect((new Invite)->invitedBy())->toBeInstanceOf(MorphTo::class);
});
