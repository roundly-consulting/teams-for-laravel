<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use RoundlyConsulting\Teams\Support\TeamsConfig;
use RoundlyConsulting\Teams\Traits\ScopedToTeam;

/**
 * Finds the host's team-scoped models: every class listed in `teams.scoping.models`, plus
 * every concrete Eloquent model using {@see ScopedToTeam} declared in a file under
 * `teams.scoping.discover`. Files are read, never included: a class is only loaded
 * through the autoloader, by the name its file declares.
 *
 * @internal
 */
final readonly class ScopedModelFinder
{
    public function __construct(
        private Filesystem $files,
    ) {}

    /**
     * @return list<class-string> listed classes first-class: kept even when they do not
     *                            use the trait, so the doctor can say so
     */
    public function find(): array
    {
        $found = [];

        foreach (TeamsConfig::scopedModels() as $class) {
            $found[ltrim($class, '\\')] = true;
        }

        foreach (TeamsConfig::scopedModelPaths() as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = self::declaredClass($file->getContents());

                if ($class !== null && self::isScopedModel($class)) {
                    $found[$class] = true;
                }
            }
        }

        /** @var list<class-string> $classes */
        $classes = array_keys($found);
        sort($classes);

        return $classes;
    }

    public static function usesTrait(string $class): bool
    {
        return class_exists($class) && in_array(ScopedToTeam::class, class_uses_recursive($class), true);
    }

    private static function isScopedModel(string $class): bool
    {
        return class_exists($class)
            && is_subclass_of($class, Model::class)
            && ! (new ReflectionClass($class))->isAbstract()
            && self::usesTrait($class);
    }

    private static function declaredClass(string $source): ?string
    {
        if (preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $source, $class) !== 1) {
            return null;
        }

        $namespace = preg_match('/^\s*namespace\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*;/m', $source, $match) === 1 ? $match[1].'\\' : '';

        return $namespace.$class[1];
    }
}
