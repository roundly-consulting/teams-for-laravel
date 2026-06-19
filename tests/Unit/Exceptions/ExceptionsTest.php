<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Exceptions\InviteEmailMismatchException;
use RoundlyConsulting\Teams\Exceptions\InviteExpiredException;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Models\Invite;

it('builds an expired exception with a translated message', function () {
    $invite = Invite::factory()->make(['code' => 'ABC123']);

    $exception = InviteExpiredException::for($invite);

    expect($exception)
        ->toBeInstanceOf(TeamsException::class)
        ->and($exception->getMessage())->toBe('The invite "ABC123" has expired.');
});

it('builds an email mismatch exception with a translated message', function () {
    $invite = Invite::factory()->make(['email' => 'jane@acme.test']);

    $exception = InviteEmailMismatchException::for($invite);

    expect($exception)
        ->toBeInstanceOf(TeamsException::class)
        ->and($exception->getMessage())->toBe('This invite is addressed to "jane@acme.test".');
});
