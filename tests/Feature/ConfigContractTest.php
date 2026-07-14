<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * Every `config('teams.…')` key the source reads must exist in the SHIPPED
 * config file. A key the code reads but the package never ships is unreachable
 * for a host — and invisible to a suite that sets the key by hand (shops shipped
 * a whole store-credit feature behind `shops.payments.*` while the config file
 * defined `payment`, and 330 green tests set the same wrong key the code read).
 */
it('ships every config key the source reads', function (): void {
    /** @var array<string, mixed> $shipped */
    $shipped = require __DIR__.'/../../config/teams.php';

    $read = [];

    foreach (sourceFiles() as $file) {
        preg_match_all(
            "/config\(\s*'teams\.([a-z0-9_.]+)'/",
            (string) file_get_contents($file),
            $matches,
        );

        foreach ($matches[1] as $key) {
            $read[$key] = $file;
        }
    }

    // Guard the guard: the package really does read config, so an empty scrape
    // can't make this test pass vacuously.
    expect($read)->not->toBeEmpty();

    foreach ($read as $key => $file) {
        expect(Arr::has($shipped, $key))->toBeTrue(
            "config/teams.php ships no \"{$key}\" key, but ".basename((string) $file).' reads it',
        );
    }
});

/** @return list<string> */
function sourceFiles(): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../../src'),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}
