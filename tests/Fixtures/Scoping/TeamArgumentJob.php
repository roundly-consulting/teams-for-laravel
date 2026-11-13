<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RoundlyConsulting\Teams\Contracts\WithoutTeamContext;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RuntimeException;

/**
 * A platform job that receives its team (seat sync, metering): marked to carry no team
 * context, it records the ambient context it sees and the rows of the team it was given.
 */
final class TeamArgumentJob implements ShouldQueue, WithoutTeamContext
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var list<array<string, mixed>> */
    public static array $seen = [];

    public function __construct(
        public int|string $teamId,
        public bool $fail = false,
        public ?object $then = null,
    ) {}

    public function handle(): void
    {
        self::record($this->teamId);

        if ($this->then !== null) {
            dispatch($this->then);
        }

        if ($this->fail) {
            throw new RuntimeException('The platform job failed.');
        }
    }

    public static function record(int|string $teamId): void
    {
        $current = Teams::current()?->getKey();
        $team = Team::query()->findOrFail($teamId);

        self::$seen[] = [
            'current' => is_int($current) || is_string($current) ? $current : null,
            'many' => Teams::currentMany()->map(fn ($team) => $team->getKey())->values()->all(),
            'ambient' => Project::query()->orderBy('name')->pluck('name')->all(),
            'own' => Project::forTeam($team)->orderBy('name')->pluck('name')->all(),
        ];
    }
}
