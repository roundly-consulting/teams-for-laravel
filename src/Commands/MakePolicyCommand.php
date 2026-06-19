<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakePolicyCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:policy {name} {--force : Overwrite the policy if it already exists}';

    /** @var string */
    protected $description = 'Generate a team-scoped policy class';

    public function handle(Filesystem $files): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        $class = Str::studly($name);
        $path = app_path("Policies/{$class}.php");

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("Policy {$class} already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));

        $stub = (string) $files->get(__DIR__.'/../../stubs/TeamPolicy.stub');

        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$this->policyNamespace(), $class],
            $stub,
        );

        $files->put($path, $contents);

        $this->info("Policy [{$path}] created successfully.");

        return self::SUCCESS;
    }

    private function policyNamespace(): string
    {
        $appNamespace = trim($this->laravel->getNamespace(), '\\');

        return $appNamespace.'\\Policies';
    }
}
