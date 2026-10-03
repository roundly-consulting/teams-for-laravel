<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * The command writes into app/Policies: a throwaway app/ per test, never the shared testbench
 * skeleton every parallel process boots from. The app path is read when the command runs, so
 * pointing it here is enough. The namespace is resolved first — Laravel derives it by
 * matching app/ against the skeleton's composer.json, which a sandbox would not match.
 */
beforeEach(function (): void {
    $this->app->getNamespace();
    $this->app->useAppPath($this->sandbox = sys_get_temp_dir().'/teams-policy-'.bin2hex(random_bytes(6)));
});

afterEach(fn () => File::deleteDirectory($this->sandbox));

it('generates into the sandbox, never the shared skeleton', function (): void {
    expect(app_path('Policies'))->toContain('teams-policy-');
});

it('generates a policy file', function (): void {
    $this->artisan('teams:policy', ['name' => 'PostPolicy'])
        ->assertSuccessful();

    $path = app_path('Policies/PostPolicy.php');

    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))
        ->toContain('class PostPolicy extends AbstractTeamPolicy')
        ->toContain('Policies');
});

it('refuses to overwrite without --force', function (): void {
    File::ensureDirectoryExists(app_path('Policies'));
    File::put(app_path('Policies/PostPolicy.php'), '<?php // existing');

    $this->artisan('teams:policy', ['name' => 'PostPolicy'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(File::get(app_path('Policies/PostPolicy.php')))->toContain('// existing');
});

it('overwrites with --force', function (): void {
    File::ensureDirectoryExists(app_path('Policies'));
    File::put(app_path('Policies/PostPolicy.php'), '<?php // existing');

    $this->artisan('teams:policy', ['name' => 'PostPolicy', '--force' => true])
        ->assertSuccessful();

    expect(File::get(app_path('Policies/PostPolicy.php')))->not->toContain('// existing');
});
