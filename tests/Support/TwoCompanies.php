<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Support;

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Document;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Task;
use RoundlyConsulting\Teams\Tests\User;

/**
 * Two customer companies holding IDENTICAL data — same project names, same task titles —
 * so any leak shows up as a doubled row rather than hiding behind a different value.
 */
final readonly class TwoCompanies
{
    private function __construct(
        public Team $a,
        public Team $b,
        public User $alice,
        public User $bob,
    ) {}

    public static function make(): self
    {
        $a = Team::factory()->create(['name' => 'Acme']);
        $b = Team::factory()->create(['name' => 'Globex']);
        $alice = User::create();
        $bob = User::create();

        Teams::for($a)->members()->add($alice, 'user');
        Teams::for($b)->members()->add($bob, 'user');

        foreach ([$a, $b] as $team) {
            Teams::runAs($team, static function (): void {
                foreach (['Apollo', 'Gemini'] as $name) {
                    $project = Project::create(['name' => $name]);
                    Task::create(['project_id' => $project->getKey(), 'title' => $name.' kickoff']);
                }

                Document::create(['title' => 'Contract']);
            });
        }

        Teams::setCurrent(null);

        return new self($a, $b, $alice, $bob);
    }

    public function projectOf(Team $team, string $name = 'Apollo'): Project
    {
        /** @var Project */
        return Project::forTeam($team)->where('name', $name)->firstOrFail();
    }
}
