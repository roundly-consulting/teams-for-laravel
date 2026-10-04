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

it('resolves the invite-only refusal in the current locale', function (): void {
    expect(TeamsException::joinPolicyForbidsRequests()->getMessage())
        ->toBe('This team is invite-only and does not accept join requests.');

    app()->setLocale('sk');

    expect(TeamsException::joinPolicyForbidsRequests()->getMessage())
        ->toBe('Tento tím je prístupný len na pozvanie a neprijíma žiadosti o pripojenie.');
});

it('pluralises the seat limit in the current locale', function (string $locale, int $seats, string $expected): void {
    app()->setLocale($locale);

    expect(TeamsException::maxSeatsReached($seats)->getMessage())->toBe($expected);
})->with([
    'en 1' => ['en', 1, 'This team has reached its seat limit (1 seat).'],
    'en 3' => ['en', 3, 'This team has reached its seat limit (3 seats).'],
    'sk 0' => ['sk', 0, 'Kapacita tohto tímu (0 miest) je naplnená.'],
    'sk 1' => ['sk', 1, 'Kapacita tohto tímu (1 miesto) je naplnená.'],
    'sk 3' => ['sk', 3, 'Kapacita tohto tímu (3 miesta) je naplnená.'],
    'sk 5' => ['sk', 5, 'Kapacita tohto tímu (5 miest) je naplnená.'],
]);

it('keeps a published seat-limit override without plural forms working', function (): void {
    app('translator')->addLines(['errors.max_seats_reached' => 'No free seats left (limit :count).'], 'en', 'teams');

    expect(TeamsException::maxSeatsReached(3)->getMessage())->toBe('No free seats left (limit 3).');
});
