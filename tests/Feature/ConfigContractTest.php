<?php

declare(strict_types=1);

/**
 * C — the config contract, pinned in both directions.
 *
 * This file replaces a hand-rolled forward-only check. Two things it could not do:
 *
 *  - It **regexed** `config('teams.…')` out of the raw file text rather than tokenizing, so
 *    a config key merely *mentioned in a docblock* counted as a read — the exact trap media
 *    #27 fell into — while an injected `Repository::get()` or a `Config::get()` read was
 *    invisible to it. It also could not see the five `teams.models.*` keys, which are read
 *    through the toolkit's ModelResolver rather than a `config(` token.
 *  - It had **no reverse direction at all**. A key the package ships but nothing reads is a
 *    documented lie: media #27's `max_file_size` cap never applied, alerts #24's
 *    thrice-documented `escalation` key did nothing. Teams is a package where that lie is
 *    expensive — a host reading `teams.members.expires_after` in the file believes
 *    memberships lapse, and `teams.invites.max_uses` that a link is single-seat.
 *
 * The forward direction it did have is kept and strengthened: shops #18 shipped a whole
 * store-credit feature behind `shops.payments.*` while the file defined `payment.*`, and 330
 * green tests set the same wrong key the code read.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/teams.php')->toSatisfyConfigContract(
        [__DIR__.'/../../src', __DIR__.'/../../database'],
        [
            // The five `teams.models.*` keys are read through the toolkit's
            // `ModelResolver::for(…)` seam (via the Support\*Model classes), not as `config(`
            // tokens, so the prefix is what makes those real reads visible to the scraper.
            'extraReadPrefixes' => ['teams.'],

            // Deliberately NO `excludeFromReverse` for the provider. The testing README's
            // example excludes the service provider on the grounds that "a render is not a
            // read" — but the toolkit's PackageServiceProvider both `contributesToAbout()`
            // and does real config reads in one file, and this provider branches on
            // `teams.gate.register` for real. Excluding it would discard the only reader of
            // several bound keys and weaken the reverse direction for nothing.
        ],
    );
});
