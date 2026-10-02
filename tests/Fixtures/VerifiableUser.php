<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures;

use Illuminate\Auth\MustVerifyEmail as VerifiesEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use RoundlyConsulting\Teams\Tests\User;

/**
 * A host user that verifies its email — what the accept-invite stub trusts.
 */
class VerifiableUser extends User implements MustVerifyEmail
{
    use VerifiesEmail;

    /** @var string */
    protected $table = 'users';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime'];
    }
}
