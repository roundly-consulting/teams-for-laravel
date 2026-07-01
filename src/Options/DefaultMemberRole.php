<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * Per-team override for the role assigned to new members when none is supplied.
 * Null (the default) falls back to config('teams.roles.default').
 */
final class DefaultMemberRole extends BaseOption
{
    public function castAs(): string
    {
        return 'string';
    }

    public function default(): ?string
    {
        return null;
    }
}
