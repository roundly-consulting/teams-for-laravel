<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;

/**
 * Records what a queued job sees: its team context and the scoped rows.
 */
final class ProbeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var list<array<string, mixed>> */
    public static array $seen = [];

    public function handle(): void
    {
        $current = Teams::current()?->getKey();

        self::$seen[] = [
            'current' => is_int($current) || is_string($current) ? $current : null,
            'many' => Teams::currentMany()->map(fn ($team) => $team->getKey())->values()->all(),
            'projects' => Project::query()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
