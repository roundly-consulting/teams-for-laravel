<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

afterEach(function (): void {
    File::delete(app_path('Policies/PostPolicy.php'));
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
